<?php
declare(strict_types = 1);

namespace TYPO3\CMS\FrontendLogin\Service;

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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\FrontendLogin\Configuration\IncompleteConfigurationException;
use TYPO3\CMS\FrontendLogin\Configuration\RecoveryConfiguration;
use TYPO3\CMS\FrontendLogin\Domain\Repository\FrontendUserRepository;
use TYPO3\CMS\FrontendLogin\Event\SendRecoveryEmailEvent;

/**
 * @internal this is a concrete TYPO3 implementation and solely used for EXT:felogin and not part of TYPO3's Core API.
 */
class RecoveryService implements RecoveryServiceInterface
{
    /**
     * @var RecoveryConfiguration
     */
    protected $recoveryConfiguration;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var FrontendUserRepository
     */
    protected $userRepository;

    /**
     * @var LanguageService
     */
    protected $languageService;

    /**
     * @param Mailer $mailer
     * @param EventDispatcherInterface $eventDispatcher
     * @param ConfigurationManager $configurationManager
     * @param RecoveryConfiguration $recoveryConfiguration
     * @param UriBuilder $uriBuilder
     * @param FrontendUserRepository $userRepository
     * @param LanguageService $languageService
     * @throws InvalidConfigurationTypeException
     */
    public function __construct(
        Mailer $mailer,
        EventDispatcherInterface $eventDispatcher,
        ConfigurationManager $configurationManager,
        RecoveryConfiguration $recoveryConfiguration,
        UriBuilder $uriBuilder,
        FrontendUserRepository $userRepository,
        LanguageService $languageService
    ) {
        $this->mailer = $mailer;
        $this->eventDispatcher = $eventDispatcher;
        $this->settings = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS);
        $this->recoveryConfiguration = $recoveryConfiguration;
        $this->uriBuilder = $uriBuilder;
        $this->userRepository = $userRepository;
        $this->languageService = $languageService;
    }

    /**
     * Sends an email with an absolute link including a forgot hash to the passed email address
     * with instructions to recover the account.
     *
     * @param string $emailAddress Receiver's email address.
     *
     * @throws TransportExceptionInterface
     * @throws IncompleteConfigurationException
     */
    public function sendRecoveryEmail(string $emailAddress): void
    {
        $hash = $this->recoveryConfiguration->getForgotHash();
        $this->userRepository->updateForgotHashForUserByEmail($emailAddress, $hash);
        $userInformation = $this->userRepository->fetchUserInformationByEmail($emailAddress);
        $receiver = new Address($emailAddress, $this->getReceiverName($userInformation));
        $email = $this->prepareMail($receiver, $hash);

        $event = new SendRecoveryEmailEvent($email, $userInformation);
        $this->eventDispatcher->dispatch($event);
        $this->mailer->send($event->getEmail());
    }

    /**
     * Get display name from values. Fallback to username if none of the "_name" fields is set.
     *
     * @param array $userInformation
     *
     * @return string
     */
    protected function getReceiverName(array $userInformation): string
    {
        $displayName = trim(
            sprintf(
                '%s%s%s',
                $userInformation['first_name'],
                $userInformation['middle_name'] ? " {$userInformation['middle_name']}" : '',
                $userInformation['last_name'] ? " {$userInformation['last_name']}" : ''
            )
        );

        return $displayName ?: $userInformation['username'];
    }

    /**
     * Create email object from configuration.
     *
     * @param Address $receiver
     * @param string $hash
     * @return Email
     * @throws IncompleteConfigurationException
     */
    protected function prepareMail(Address $receiver, string $hash): Email
    {
        $url = $this->uriBuilder->setCreateAbsoluteUri(true)
            ->uriFor(
                'showChangePassword',
                ['hash' => $hash],
                'PasswordRecovery',
                'felogin',
                'Login'
            );

        $variables = [
            'receiverName' => $receiver->getName(),
            'url' => $url,
            'validUntil' => date($this->settings['dateFormat'], $this->recoveryConfiguration->getLifeTimeTimestamp()),
        ];

        $plainMailTemplate = $this->recoveryConfiguration->getPlainMailTemplate();
        $plainMailTemplate->assignMultiple($variables);

        $subject = $this->languageService->sL('LLL:EXT:felogin/Resources/Private/Language/locallang.xlf:password_recovery_mail_header');
        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail
            ->subject($subject)
            ->from($this->recoveryConfiguration->getSender())
            ->to($receiver)
            ->text($plainMailTemplate->render());

        if ($this->recoveryConfiguration->hasHtmlMailTemplate()) {
            $htmlMailTemplate = $this->recoveryConfiguration->getHtmlMailTemplate();
            $htmlMailTemplate->assignMultiple($variables);
            $mail->html($htmlMailTemplate->render());
        }

        $replyTo = $this->recoveryConfiguration->getReplyTo();
        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        }

        return $mail;
    }
}
