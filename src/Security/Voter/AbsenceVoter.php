<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Absence;
use App\Entity\AbsenceStatus;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Absence>
 */
final class AbsenceVoter extends Voter
{
    /** See type and comment (owner, own team lead, admin). */
    public const VIEW_DETAILS = 'ABSENCE_VIEW_DETAILS';
    /** Approve or reject: team lead of the owner's team (not own requests) or admin. */
    public const DECIDE = 'ABSENCE_DECIDE';
    /** Cancel: owner or admin, while requested/approved. */
    public const CANCEL = 'ABSENCE_CANCEL';

    public function __construct(private readonly AccessDecisionManagerInterface $decisionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW_DETAILS, self::DECIDE, self::CANCEL], true)
            && $subject instanceof Absence;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $isAdmin = $this->decisionManager->decide($token, [User::ROLE_ADMIN]);
        $isOwner = $subject->getUser() === $user;
        $isTeamLead = $this->decisionManager->decide($token, [User::ROLE_TEAM_LEAD])
            && $user->isLeadOf($subject->getUser()->getTeam());

        return match ($attribute) {
            self::VIEW_DETAILS => $isOwner || $isAdmin || $isTeamLead,
            self::DECIDE => $subject->getStatus() === AbsenceStatus::REQUESTED
                && ($isAdmin || ($isTeamLead && !$isOwner)),
            self::CANCEL => $subject->isActive() && ($isOwner || $isAdmin),
            default => false,
        };
    }
}
