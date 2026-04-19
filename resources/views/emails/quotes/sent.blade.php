<x-mail::message>
# Your quote is ready

Hi {{ $customer->first_name }},

{{ $accountName }} has prepared a quote for **{{ $estimate->title ?? 'your project' }}**@if($estimate->total_sqft) ({{ number_format($estimate->total_sqft) }} sq ft)@endif.

When you're ready to authorize the work, use the button below to begin the approval process. You can leave and return anytime using the same link.

<x-mail::button :url="$approvalUrl">
Begin approval
</x-mail::button>

This link is personal to you — please don't forward it.

Thanks,<br>
{{ $accountName }}
</x-mail::message>
