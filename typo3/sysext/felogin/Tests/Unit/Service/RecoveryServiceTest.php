<?php
declare(strict_types = 1);

namespace TYPO3\CMS\FrontendLogin\Tests\Unit\Service;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Generator;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\FrontendLogin\Configuration\RecoveryConfiguration;
use TYPO3\CMS\FrontendLogin\Domain\Repository\FrontendUserRepository;
use TYPO3\CMS\FrontendLogin\Service\RecoveryService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RecoveryServiceTest extends UnitTestCase
{
    /**
     * @var bool
     */
    protected $resetSingletonInstances = true;

    /**
     * @var FrontendUserRepository|ObjectProphecy
     */
    protected $userRepository;

    /**
     * @var RecoveryConfiguration|ObjectProphecy
     */
    protected $recoveryConfiguration;

    protected function setUp(): void
    {
        $this->userRepository = $this->prophesize(FrontendUserRepository::class);
        $this->recoveryConfiguration = $this->prophesize(RecoveryConfiguration::class);
    }

    /**
     * @test
     * @dataProvider configurationDataProvider
     * @param string $emailAddress
     * @param array $recoveryConfiguration
     * @param array $userInformation
     * @param Address $receiver
     * @param array $settings
     */
    public function sendRecoveryEmailShouldGenerateMailFromConfiguration(
        string $emailAddress,
        array $recoveryConfiguration,
        array $userInformation,
        Address $receiver,
        array $settings
    ): void {
        $expectedMail = new MailMessage();
        $expectedMail->subject('translation')
            ->from($recoveryConfiguration['sender'])
            ->to($receiver)
            ->text('plain mail template');
        $expectedViewVariables = [
            'receiverName' => $receiver->getName(),
            'url' => 'some uri',
            'validUntil' => date($settings['dateFormat'], $recoveryConfiguration['lifeTimeTimestamp'])
        ];

        $plainMailTemplate = $this->prophesize(StandaloneView::class);
        $plainMailTemplate->assignMultiple($expectedViewVariables)->shouldBeCalledOnce();
        $plainMailTemplate->render()->willReturn('plain mail template');
        $htmlMailTemplate = $this->prophesize(StandaloneView::class);

        if ($recoveryConfiguration['hasHtmlMailTemplate']) {
            $this->recoveryConfiguration->getHtmlMailTemplate()->willReturn($htmlMailTemplate->reveal());
            $htmlMailTemplate->assignMultiple($expectedViewVariables)->shouldBeCalledOnce();
            $htmlMailTemplate->render()->willReturn('html mail template');
            $expectedMail->html('html mail template');
        }

        $this->mockRecoveryConfigurationAndUserRepository(
            $emailAddress,
            $recoveryConfiguration,
            $userInformation,
            $plainMailTemplate
        );

        $configurationManager = $this->prophesize(ConfigurationManager::class);
        $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS)->willReturn($settings);

        $languageService = $this->prophesize(LanguageService::class);
        $languageService->sL(Argument::containingString('password_recovery_mail_header'))->willReturn('translation');

        $uriBuilder = $this->prophesize(UriBuilder::class);
        $uriBuilder->setCreateAbsoluteUri(true)->willReturn($uriBuilder->reveal());
        $uriBuilder->uriFor(
            'showChangePassword',
            ['hash' => $recoveryConfiguration['forgotHash']],
            'PasswordRecovery',
            'felogin',
            'Login'
        )->willReturn('some uri');

        $mailer = $this->prophesize(Mailer::class);

        GeneralUtility::addInstance(MailMessage::class, new MailMessage());

        $eventDispatcherProphecy = $this->prophesize(EventDispatcherInterface::class);
        $subject = new RecoveryService(
            $mailer->reveal(),
            $eventDispatcherProphecy->reveal(),
            $configurationManager->reveal(),
            $this->recoveryConfiguration->reveal(),
            $uriBuilder->reveal(),
            $this->userRepository->reveal(),
            $languageService->reveal()
        );

        $subject->sendRecoveryEmail($emailAddress);

        $mailer->send($expectedMail)->shouldHaveBeenCalledOnce();
    }

    public function configurationDataProvider(): Generator
    {
        yield 'minimal configuration' => [
            'email' => 'max@mustermann.de',
            'recoveryConfiguration' => [
                'lifeTimeTimestamp' => 1234567899,
                'forgotHash' => '0123456789|some hash',
                'sender' => new Address('typo3@typo3.typo3', 'TYPO3 Installation'),
                'hasHtmlMailTemplate' => false,
                'replyTo' => null
            ],
            'userInformation' => [
                'first_name' => '',
                'middle_name' => '',
                'last_name' => '',
                'username' => 'm.mustermann'
            ],
            'receiver' => new Address('max@mustermann.de', 'm.mustermann'),
            'settings' => ['dateFormat' => 'Y-m-d H:i']
        ];
        yield 'html mail provided' => [
            'email' => 'max@mustermann.de',
            'recoveryConfiguration' => [
                'lifeTimeTimestamp' => 123456789,
                'forgotHash' => '0123456789|some hash',
                'sender' => new Address('typo3@typo3.typo3', 'TYPO3 Installation'),
                'hasHtmlMailTemplate' => true,
                'replyTo' => null
            ],
            'userInformation' => [
                'first_name' => '',
                'middle_name' => '',
                'last_name' => '',
                'username' => 'm.mustermann'
            ],
            'receiver' => new Address('max@mustermann.de', 'm.mustermann'),
            'settings' => ['dateFormat' => 'Y-m-d H:i']
        ];
        yield 'complex display name instead of username' => [
            'email' => 'max@mustermann.de',
            'recoveryConfiguration' => [
                'lifeTimeTimestamp' => 123456789,
                'forgotHash' => '0123456789|some hash',
                'sender' => new Address('typo3@typo3.typo3', 'TYPO3 Installation'),
                'hasHtmlMailTemplate' => true,
                'replyTo' => null
            ],
            'userInformation' => [
                'first_name' => 'Max',
                'middle_name' => 'Maximus',
                'last_name' => 'Mustermann',
                'username' => 'm.mustermann'
            ],
            'receiver' => new Address('max@mustermann.de', 'Max Maximus Mustermann'),
            'settings' => ['dateFormat' => 'Y-m-d H:i']
        ];
        yield 'custom dateFormat and no middle name' => [
            'email' => 'max@mustermann.de',
            'recoveryConfiguration' => [
                'lifeTimeTimestamp' => 987654321,
                'forgotHash' => '0123456789|some hash',
                'sender' => new Address('typo3@typo3.typo3', 'TYPO3 Installation'),
                'hasHtmlMailTemplate' => true,
                'replyTo' => null
            ],
            'userInformation' => [
                'first_name' => 'Max',
                'middle_name' => '',
                'last_name' => 'Mustermann',
                'username' => 'm.mustermann'
            ],
            'receiver' => new Address('max@mustermann.de', 'Max Mustermann'),
            'settings' => ['dateFormat' => 'Y-m-d']
        ];
    }

    /**
     * @param string $emailAddress
     * @param array $recoveryConfiguration
     * @param array $userInformation
     * @param ObjectProphecy $plainMailTemplate
     */
    protected function mockRecoveryConfigurationAndUserRepository(
        string $emailAddress,
        array $recoveryConfiguration,
        array $userInformation,
        ObjectProphecy $plainMailTemplate
    ): void {
        $this->recoveryConfiguration->getForgotHash()->willReturn($recoveryConfiguration['forgotHash']);
        $this->recoveryConfiguration->getLifeTimeTimestamp()->willReturn($recoveryConfiguration['lifeTimeTimestamp']);
        $this->recoveryConfiguration->getPlainMailTemplate()->willReturn($plainMailTemplate->reveal());
        $this->recoveryConfiguration->getSender()->willReturn($recoveryConfiguration['sender']);
        $this->recoveryConfiguration->hasHtmlMailTemplate()->willReturn($recoveryConfiguration['hasHtmlMailTemplate']);
        $this->recoveryConfiguration->getReplyTo()->willReturn($recoveryConfiguration['replyTo']);

        $this->userRepository->updateForgotHashForUserByEmail($emailAddress, $recoveryConfiguration['forgotHash'])
            ->shouldBeCalledOnce();
        $this->userRepository->fetchUserInformationByEmail($emailAddress)
            ->willReturn($userInformation);
    }
}
