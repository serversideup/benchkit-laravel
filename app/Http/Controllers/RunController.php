<?php

namespace App\Http\Controllers;

use App\Actions\Runs\CreateRunSnapshot;
use App\Actions\Runs\DeleteRun;
use App\Actions\Runs\FindRun;
use App\Actions\Runs\ListRuns;
use App\Actions\Runs\UpdateRunMeta;
use App\Http\Requests\Runs\StoreRunRequest;
use App\Http\Requests\Runs\UpdateRunRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;

class RunController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('Runs/Index', [
            'runs' => (new ListRuns)->execute(),
        ]);
    }

    public function show(string $id): \Inertia\Response
    {
        $run = (new FindRun)->execute($id);

        abort_if($run === null, 404);

        return Inertia::render('Runs/Show', [
            'run' => $run,
        ]);
    }

    public function compare(string $a, string $b): \Inertia\Response
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

    public function destroy(string $id): Response
    {
        abort_unless((new DeleteRun)->execute($id), 404);

        return response()->noContent();
    }
}
