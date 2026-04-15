<?php

namespace App\Http\Controllers\Sysops;

use App\Ai\Agents\FloorPlanExtractionAgent;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sysops\AiAgentUpdateRequest;
use App\Models\AiAgentSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AiAgentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sysops/ai-agents/index', [
            'agents' => AiAgentSetting::query()
                ->orderBy('label')
                ->get(['id', 'kind', 'label', 'description', 'updated_at']),
        ]);
    }

    public function edit(AiAgentSetting $aiAgentSetting): Response
    {
        return Inertia::render('sysops/ai-agents/edit', [
            'agent' => $aiAgentSetting,
            'responseFormat' => FloorPlanExtractionAgent::RESPONSE_FORMAT_JSON,
        ]);
    }

    public function update(
        AiAgentUpdateRequest $request,
        AiAgentSetting $aiAgentSetting,
    ): RedirectResponse {
        $aiAgentSetting->update($request->validated());

        ActivityLogger::event(
            ActivityEvent::AiAgentSettingUpdated,
            metadata: [
                'ai_agent_setting_id' => $aiAgentSetting->id,
                'kind' => $aiAgentSetting->kind->value,
            ],
            user: $request->user(),
        );

        return back()->with('status', 'ai-agent-updated');
    }
}
