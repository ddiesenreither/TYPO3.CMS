<?php
declare(strict_types = 1);
namespace TYPO3\CMS\FrontendLogin\Event;

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

use Psr\EventDispatcher\StoppableEventInterface;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;

/**
 * Event that contains information about the password which was set, and is about to be stored in the database.
 *
 * Additional validation can happen here.
 */
final class PasswordChangeEvent implements StoppableEventInterface
{
    /**
     * @var bool
     */
    private $invalid = false;

    /**
     * @var string
     */
    private $errorMessage;

    /**
     * @var FrontendUser
     */
    private $user;

    /**
     * @var string
     */
    private $passwordHash;

    /**
     * @var string
     */
    private $rawPassword;

    public function __construct(FrontendUser $user, string $newPasswordHash, string $rawNewPassword)
    {
        $this->user = $user;
        $this->passwordHash = $newPasswordHash;
        $this->rawPassword = $rawNewPassword;
    }

    public function getUser(): FrontendUser
    {
        return $this->user;
    }

    public function getHashedPassword(): string
    {
        return $this->passwordHash;
    }

    public function setHashedPassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getRawPassword(): string
    {
        return $this->rawPassword;
    }

    public function setAsInvalid(string $message)
    {
        $this->invalid = true;
        $this->errorMessage = $message;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function isPropagationStopped(): bool
    {
        return $this->invalid;
    }
}
