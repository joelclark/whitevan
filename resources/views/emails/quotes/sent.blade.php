<x-mail::message>
# Your quote is ready

Hi {{ $customer->first_name }},

{{ $accountName }} has prepared a quote for **{{ $estimate->title ?? 'your project' }}**@if($estimate->total_sqft) ({{ number_format($estimate->total_sqft) }} sq ft)@endif. Use the link below to view the full breakdown and accept when you're ready. You can come back to the same link anytime.

<x-mail::button :url="$approvalUrl">
View and accept your quote
</x-mail::button>

This link is personal to you — please don't forward it.

Thanks,<br>
{{ $accountName }}
</x-mail::message>
