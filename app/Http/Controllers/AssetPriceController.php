<?php

namespace App\Http\Controllers;

use App\Domains\AssetView\Ports\AssetMetaViewPort;

class AssetPriceController extends Controller
{
    public function show(int $assetId, AssetMetaViewPort $metaViewPort)
    {
        $meta = $metaViewPort->getMeta($assetId);

        if ($meta === null) {
            abort(404, 'Asset not found');
        }

        return view('asset-price-simple', ['assetId' => $assetId, 'meta' => $meta]);
    }
}
