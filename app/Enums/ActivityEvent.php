<?php

namespace App\Enums;

enum ActivityEvent: string
{
    case UserLoggedIn = 'user.logged_in';
    case UserPasswordReset = 'user.password_reset';
    case UserTwoFactorEnabled = 'user.two_factor_enabled';
    case UserTwoFactorDisabled = 'user.two_factor_disabled';
    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case UserSecurityGroupAdded = 'user.security_group_added';
    case UserSecurityGroupRemoved = 'user.security_group_removed';
    case SysopImpersonationStarted = 'sysop.impersonation_started';
    case SysopImpersonationStopped = 'sysop.impersonation_stopped';
    case CustomerCreated = 'customer.created';
    case CustomerUpdated = 'customer.updated';
    case CustomerDeleted = 'customer.deleted';
    case EstimateCreated = 'estimate.created';
    case EstimateUpdated = 'estimate.updated';
    case EstimateDeleted = 'estimate.deleted';
    case EstimateAgentFailed = 'estimate.agent_failed';
    case EstimateFloorplanAssetsFailed = 'estimate.floorplan_assets_failed';
    case EstimateInterviewCompleted = 'estimate.interview_completed';
    case AiAgentSettingUpdated = 'ai.agent_setting_updated';

    public function label(): string
    {
        return match ($this) {
            self::UserLoggedIn => 'User logged in',
            self::UserPasswordReset => 'Password reset',
            self::UserTwoFactorEnabled => 'Two-factor authentication enabled',
            self::UserTwoFactorDisabled => 'Two-factor authentication disabled',
            self::UserActivated => 'User activated',
            self::UserDeactivated => 'User deactivated',
            self::UserSecurityGroupAdded => 'Security group added',
            self::UserSecurityGroupRemoved => 'Security group removed',
            self::SysopImpersonationStarted => 'Sysop impersonation started',
            self::SysopImpersonationStopped => 'Sysop impersonation stopped',
            self::CustomerCreated => 'Customer created',
            self::CustomerUpdated => 'Customer updated',
            self::CustomerDeleted => 'Customer deleted',
            self::EstimateCreated => 'Estimate created',
            self::EstimateUpdated => 'Estimate updated',
            self::EstimateDeleted => 'Estimate deleted',
            self::EstimateAgentFailed => 'Estimate agent run failed',
            self::EstimateFloorplanAssetsFailed => 'Estimate floorplan asset extraction failed',
            self::EstimateInterviewCompleted => 'Estimate interview completed',
            self::AiAgentSettingUpdated => 'AI agent setting updated',
        };
    }
}
