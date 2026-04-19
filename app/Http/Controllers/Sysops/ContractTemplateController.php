<?php

namespace App\Http\Controllers\Sysops;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sysops\ContractTemplateUpdateRequest;
use App\Models\ContractTemplate;
use App\Services\ActivityLogger;
use App\Services\ContractMarkdown;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContractTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sysops/contract-templates/index', [
            'templates' => ContractTemplate::query()
                ->orderBy('label')
                ->get(['id', 'kind', 'label', 'updated_at']),
        ]);
    }

    public function edit(
        ContractTemplate $contractTemplate,
        ContractMarkdown $markdown,
    ): Response {
        return Inertia::render('sysops/contract-templates/edit', [
            'template' => [
                'id' => $contractTemplate->id,
                'kind' => $contractTemplate->kind,
                'label' => $contractTemplate->label,
                'body' => $contractTemplate->body,
                'preview_html' => $markdown->toHtml($contractTemplate->body),
                'updated_at' => $contractTemplate->updated_at,
            ],
        ]);
    }

    public function update(
        ContractTemplateUpdateRequest $request,
        ContractTemplate $contractTemplate,
    ): RedirectResponse {
        $contractTemplate->update($request->validated());

        ActivityLogger::event(
            ActivityEvent::ContractTemplateUpdated,
            metadata: [
                'contract_template_id' => $contractTemplate->id,
                'kind' => $contractTemplate->kind,
            ],
            user: $request->user(),
        );

        return back()->with('status', 'contract-template-updated');
    }
}
