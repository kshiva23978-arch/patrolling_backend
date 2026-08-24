<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Deletes $model, turning a foreign-key restrict violation — the record
     * is still referenced elsewhere (e.g. a range with existing patrol
     * entries) — into a clear 409 response instead of letting the raw
     * database error (and its connection details) reach the client.
     */
    protected function deleteOrConflict(Model $model, string $label): JsonResponse
    {
        try {
            $model->delete();
        } catch (QueryException $e) {
            if (! $this->isForeignKeyRestriction($e)) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'message' => "This {$label} is still in use elsewhere and can't be deleted.",
                'data' => null,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => ucfirst($label).' deleted successfully.',
            'data' => null,
        ]);
    }

    private function isForeignKeyRestriction(QueryException $e): bool
    {
        // Postgres: 23001 (restrict_violation), 23503 (foreign_key_violation).
        // MySQL/MariaDB: 1451 (Cannot delete or update a parent row).
        if (in_array((string) $e->getCode(), ['23001', '23503', '1451'], true)) {
            return true;
        }

        // Fallback for drivers that don't surface the SQLSTATE via getCode().
        $message = $e->getMessage();

        return str_contains($message, 'RESTRICT')
            || str_contains($message, 'foreign key constraint');
    }
}
