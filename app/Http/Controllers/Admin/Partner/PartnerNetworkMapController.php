<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;

class PartnerNetworkMapController extends Controller
{
    /**
     * Slice: interactive network map with draggable markers and agent→reseller lines.
     * Data is mock for now — persistence & DB coordinates come in a later slice.
     */
    public function index()
    {
        $mapData = [
            'center' => ['lat' => -6.9175, 'lng' => 107.6191],
            'zoom' => 12,
            'nodes' => [
                ['id' => 'agent-1', 'type' => 'agent', 'label' => 'Agen 1', 'lat' => -6.9147, 'lng' => 107.6098],
                ['id' => 'agent-2', 'type' => 'agent', 'label' => 'Agen 2', 'lat' => -6.8950, 'lng' => 107.6530],
                ['id' => 'reseller-1', 'type' => 'reseller', 'label' => 'Reseller 1', 'lat' => -6.9260, 'lng' => 107.5890],
                ['id' => 'reseller-2', 'type' => 'reseller', 'label' => 'Reseller 2', 'lat' => -6.9020, 'lng' => 107.5750],
                ['id' => 'reseller-3', 'type' => 'reseller', 'label' => 'Reseller 3', 'lat' => -6.9380, 'lng' => 107.6250],
                ['id' => 'reseller-4', 'type' => 'reseller', 'label' => 'Reseller 4', 'lat' => -6.8810, 'lng' => 107.6380],
                ['id' => 'reseller-5', 'type' => 'reseller', 'label' => 'Reseller 5', 'lat' => -6.8700, 'lng' => 107.6700],
                ['id' => 'reseller-6', 'type' => 'reseller', 'label' => 'Reseller 6', 'lat' => -6.9080, 'lng' => 107.6920],
                ['id' => 'reseller-7', 'type' => 'reseller', 'label' => 'Reseller 7', 'lat' => -6.8890, 'lng' => 107.6050],
                ['id' => 'reseller-8', 'type' => 'reseller', 'label' => 'Reseller 8', 'lat' => -6.9450, 'lng' => 107.5980],
            ],
            'links' => [
                ['agentId' => 'agent-1', 'resellerId' => 'reseller-1'],
                ['agentId' => 'agent-1', 'resellerId' => 'reseller-2'],
                ['agentId' => 'agent-1', 'resellerId' => 'reseller-3'],
                ['agentId' => 'agent-1', 'resellerId' => 'reseller-8'],
                ['agentId' => 'agent-2', 'resellerId' => 'reseller-4'],
                ['agentId' => 'agent-2', 'resellerId' => 'reseller-5'],
                ['agentId' => 'agent-2', 'resellerId' => 'reseller-6'],
                ['agentId' => 'agent-2', 'resellerId' => 'reseller-7'],
            ],
        ];

        return view('admin.partner.network-map.index', compact('mapData'));
    }
}
