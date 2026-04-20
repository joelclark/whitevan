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
    case ProjectCreated = 'project.created';
    case ProjectUpdated = 'project.updated';
    case ProjectDeleted = 'project.deleted';
    case EstimateCreated = 'estimate.created';
    case EstimateUpdated = 'estimate.updated';
    case EstimateDeleted = 'estimate.deleted';
    case EstimateAgentFailed = 'estimate.agent_failed';
    case EstimateFloorplanAssetsFailed = 'estimate.floorplan_assets_failed';
    case EstimateInterviewCompleted = 'estimate.interview_completed';
    case EstimateQuoteSent = 'estimate.quote_sent';
    case AiAgentSettingUpdated = 'ai.agent_setting_updated';
    case ContractTemplateUpdated = 'contract.template_updated';
    case AccountContractUpdated = 'account.contract_updated';
    case AccountContractReverted = 'account.contract_reverted';
    case QuoteContractViewed = 'quote.contract_viewed';
    case QuoteContractSigned = 'quote.contract_signed';
    case DepositDefaultsUpdated = 'deposit.defaults_updated';
    case AccountDepositOverrideUpdated = 'account.deposit_override_updated';
    case AccountDepositOverrideReverted = 'account.deposit_override_reverted';
    case EstimateQuoteAccepted = 'estimate.quote_accepted';
    case EstimateQuoteChangeDetectedAtSigning = 'estimate.quote_change_detected_at_signing';

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
            self::ProjectCreated => 'Project created',
            self::ProjectUpdated => 'Project updated',
            self::ProjectDeleted => 'Project deleted',
            self::EstimateCreated => 'Estimate created',
            self::EstimateUpdated => 'Estimate updated',
            self::EstimateDeleted => 'Estimate deleted',
            self::EstimateAgentFailed => 'Estimate agent run failed',
            self::EstimateFloorplanAssetsFailed => 'Estimate floorplan asset extraction failed',
            self::EstimateInterviewCompleted => 'Estimate interview completed',
            self::EstimateQuoteSent => 'Quote sent',
            self::AiAgentSettingUpdated => 'AI agent setting updated',
            self::ContractTemplateUpdated => 'Contract template updated',
            self::AccountContractUpdated => 'Account contract updated',
            self::AccountContractReverted => 'Account contract reverted to default',
            self::QuoteContractViewed => 'Quote contract viewed',
            self::QuoteContractSigned => 'Quote contract signed',
            self::DepositDefaultsUpdated => 'Deposit defaults updated',
            self::AccountDepositOverrideUpdated => 'Account deposit override updated',
            self::AccountDepositOverrideReverted => 'Account deposit override reverted to default',
            self::EstimateQuoteAccepted => 'Quote accepted',
            self::EstimateQuoteChangeDetectedAtSigning => 'Quote change detected at signing',
        };
    }

    /**
     * Whether this event is safe to surface on the customer-facing project
     * timeline. Used by ProjectEventLogger to set `customer_visible` when a
     * row is written. Keep this exhaustive — a missing case throws
     * UnhandledMatchError, which the ActivityEventTest guardrail catches.
     */
    public function isCustomerVisible(): bool
    {
        return match ($this) {
            self::ProjectCreated,
            self::EstimateCreated,
            self::EstimateQuoteSent,
            self::QuoteContractViewed,
            self::QuoteContractSigned,
            self::EstimateQuoteAccepted => true,

            self::UserLoggedIn,
            self::UserPasswordReset,
            self::UserTwoFactorEnabled,
            self::UserTwoFactorDisabled,
            self::UserActivated,
            self::UserDeactivated,
            self::UserSecurityGroupAdded,
            self::UserSecurityGroupRemoved,
            self::SysopImpersonationStarted,
            self::SysopImpersonationStopped,
            self::CustomerCreated,
            self::CustomerUpdated,
            self::CustomerDeleted,
            self::ProjectUpdated,
            self::ProjectDeleted,
            self::EstimateUpdated,
            self::EstimateDeleted,
            self::EstimateAgentFailed,
            self::EstimateFloorplanAssetsFailed,
            self::EstimateInterviewCompleted,
            self::AiAgentSettingUpdated,
            self::ContractTemplateUpdated,
            self::AccountContractUpdated,
            self::AccountContractReverted,
            self::DepositDefaultsUpdated,
            self::AccountDepositOverrideUpdated,
            self::AccountDepositOverrideReverted,
            self::EstimateQuoteChangeDetectedAtSigning => false,
        };
    }
}
