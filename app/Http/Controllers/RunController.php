<?php

namespace App\Http\Controllers;

use App\Actions\Runs\BuildSubmissionDocument;
use App\Actions\Runs\CreateRunSnapshot;
use App\Actions\Runs\DeleteRun;
use App\Actions\Runs\EncodeSubmissionToken;
use App\Actions\Runs\FindRun;
use App\Actions\Runs\ListRuns;
use App\Actions\Runs\MarkRunSubmitted;
use App\Actions\Runs\UpdateRunMeta;
use App\Http\Requests\Runs\StoreRunRequest;
use App\Http\Requests\Runs\UpdateRunRequest;
use App\Support\SubmissionIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RunController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Runs/Index', [
            'runs' => (new ListRuns)->execute(),
        ]);
    }

    public function show(string $id): InertiaResponse
    {
        $run = (new FindRun)->execute($id);

        abort_if($run === null, 404);

        return Inertia::render('Runs/Show', [
            'run' => $run,
        ]);
    }

    public function compare(string $a, string $b): InertiaResponse
    {
        $runA = (new FindRun)->execute($a);
        $runB = (new FindRun)->execute($b);

        abort_if($runA === null || $runB === null, 404);

        return Inertia::render('Runs/Compare', [
            'runA' => $runA,
            'runB' => $runB,
            'runs' => (new ListRuns)->execute(),
        ]);
    }

    public function store(StoreRunRequest $request): JsonResponse
    {
        $snapshot = (new CreateRunSnapshot)->execute($request->validated());

        return response()->json([
            'run' => [
                'id' => $snapshot['id'],
                'created_at' => $snapshot['created_at'],
                'meta' => $snapshot['meta'],
                'stages_completed' => $snapshot['stages_completed'],
                'summary' => $snapshot['summary'],
            ],
        ], 201);
    }

    public function update(UpdateRunRequest $request, string $id): JsonResponse
    {
        $snapshot = (new UpdateRunMeta)->execute($id, $request->validated());

        abort_if($snapshot === null, 404);

        return response()->json([
            'run' => [
                'id' => $snapshot['id'],
                'meta' => $snapshot['meta'],
            ],
        ]);
    }

    /**
     * Everything the browser needs to submit this run to the community
     * gallery: the public document, the token that carries it, and the GitHub
     * issue URL to open.
     *
     * The allow-list that decides what leaves the machine runs here rather
     * than in the browser, so it is covered by tests and works the same on an
     * instance served over plain HTTP.
     */
    public function submission(string $id): JsonResponse
    {
        $run = (new FindRun)->execute($id);

        abort_if($run === null, 404);

        $document = (new BuildSubmissionDocument)->execute($run);
        $encoded = (new EncodeSubmissionToken)->execute($document);
        $issue = SubmissionIssue::for($document, $encoded['token']);

        return response()->json([
            'token' => $encoded['token'],
            'digest' => $encoded['digest'],
            'bytes' => $encoded['bytes'],
            'issue_url' => $issue['url'],
            // False when the token was too long to ride in the URL, so the UI
            // knows to ask for a paste instead of promising a one-click file.
            'prefill' => $issue['prefill'],
            'document' => $document,
        ]);
    }

    /**
     * Record that the submitter opened a submission for this run, so the app
     * can warn before they spend a GitHub round trip finding out the bot
     * already has it.
     */
    public function markSubmitted(string $id): JsonResponse
    {
        $snapshot = (new MarkRunSubmitted)->execute($id);

        abort_if($snapshot === null, 404);

        return response()->json([
            'run' => [
                'id' => $snapshot['id'],
                'submission_opened_at' => $snapshot['submission_opened_at'],
            ],
        ]);
    }

    public function destroy(string $id): Response
    {
        abort_unless((new DeleteRun)->execute($id), 404);

        return response()->noContent();
    }
}
