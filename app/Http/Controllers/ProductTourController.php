<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProductTourController extends Controller
{
    public function dismiss(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->persist($user, dismissed: true);

        return response()->json(['ok' => true, 'dismissed' => true]);
    }

    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->persist($user, dismissed: false);

        return response()->json(['ok' => true, 'dismissed' => false]);
    }

    private function persist($user, bool $dismissed): void
    {
        if (Schema::hasColumn($user->getTable(), 'tour_dismissed_at')) {
            $user->forceFill(['tour_dismissed_at' => $dismissed ? now() : null])->save();
        }

        $appearance = is_array($user->appearance_json) ? $user->appearance_json : [];
        if ($dismissed) {
            $appearance['hide_product_tour'] = true;
        } else {
            unset($appearance['hide_product_tour']);
        }
        $user->forceFill(['appearance_json' => $appearance])->save();
    }
}
