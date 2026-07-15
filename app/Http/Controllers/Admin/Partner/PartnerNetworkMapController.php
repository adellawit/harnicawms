<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner\Agent;
use App\Models\Partner\Reseller;
use App\Services\Partner\ForediPartnerImportService;

class PartnerNetworkMapController extends Controller
{
    /**
     * Interactive network map: agents at overview, resellers + lines on zoom-in.
     */
    public function index()
    {
        $agents = Agent::query()
            ->with(['customer:id,lat,long,city,address_shipping,address'])
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->where('code', '!=', ForediPartnerImportService::HOLDING_CODE)
            ->orderBy('name')
            ->get();

        $resellers = Reseller::query()
            ->with([
                'customer:id,lat,long,city,address_shipping,address',
                'agent:id,code,name',
            ])
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $agentNodes = [];
        $agentIdsOnMap = [];

        foreach ($agents as $agent) {
            $coords = $this->coordsFromCustomer($agent->customer);
            if (! $coords) {
                continue;
            }

            $id = 'agent-' . $agent->id;
            $agentIdsOnMap[$agent->id] = $id;
            $agentNodes[] = [
                'id' => $id,
                'dbId' => $agent->id,
                'type' => 'agent',
                'code' => $agent->code,
                'label' => $agent->name,
                'city' => $agent->city ?: ($agent->customer->city ?? null),
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
            ];
        }

        $resellerNodes = [];
        $links = [];

        foreach ($resellers as $reseller) {
            $coords = $this->coordsFromCustomer($reseller->customer);
            if (! $coords) {
                continue;
            }

            $resellerNodeId = 'reseller-' . $reseller->id;
            $isHoldingAgent = $reseller->agent?->code === ForediPartnerImportService::HOLDING_CODE;
            $hasAssignedAgentOnMap = $reseller->agent_id
                && isset($agentIdsOnMap[$reseller->agent_id])
                && ! $isHoldingAgent;

            $linkAgentNodeId = null;
            $linkMode = null;
            $linkDistanceKm = null;
            $displayAgentLabel = $reseller->agent?->name;
            $displayAgentCode = $reseller->agent?->code;

            if ($hasAssignedAgentOnMap) {
                $linkAgentNodeId = $agentIdsOnMap[$reseller->agent_id];
                $linkMode = 'assigned';
                $agentNode = collect($agentNodes)->firstWhere('id', $linkAgentNodeId);
                if ($agentNode) {
                    $linkDistanceKm = round($this->haversineKm(
                        $coords['lat'],
                        $coords['lng'],
                        $agentNode['lat'],
                        $agentNode['lng']
                    ), 1);
                    $displayAgentLabel = $agentNode['label'];
                    $displayAgentCode = $agentNode['code'];
                }
            } elseif ($agentNodes !== []) {
                $nearest = $this->nearestAgentNode($coords, $agentNodes);
                if ($nearest) {
                    $linkAgentNodeId = $nearest['id'];
                    $linkMode = 'nearest';
                    $linkDistanceKm = $nearest['distance_km'];
                    $displayAgentLabel = $nearest['label'];
                    $displayAgentCode = $nearest['code'];
                }
            }

            $resellerNodes[] = [
                'id' => $resellerNodeId,
                'dbId' => $reseller->id,
                'type' => 'reseller',
                'code' => $reseller->code,
                'label' => $reseller->name,
                'city' => $reseller->city ?: ($reseller->customer->city ?? null),
                'agentId' => $reseller->agent_id,
                'agentCode' => $displayAgentCode,
                'agentLabel' => $displayAgentLabel,
                'linkMode' => $linkMode,
                'linkDistanceKm' => $linkDistanceKm,
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
            ];

            if ($linkAgentNodeId) {
                $links[] = [
                    'agentId' => $linkAgentNodeId,
                    'resellerId' => $resellerNodeId,
                    'mode' => $linkMode,
                    'distanceKm' => $linkDistanceKm,
                ];
            }
        }

        $nodes = array_merge($agentNodes, $resellerNodes);
        $center = ['lat' => -2.5, 'lng' => 118.0];
        if ($agentNodes !== []) {
            $center = [
                'lat' => array_sum(array_column($agentNodes, 'lat')) / count($agentNodes),
                'lng' => array_sum(array_column($agentNodes, 'lng')) / count($agentNodes),
            ];
        }

        $mapData = [
            'center' => $center,
            'zoom' => 5,
            'resellerMinZoom' => 8,
            'coveragePaddingKm' => 15,
            'defaultCoverageKm' => 40,
            'nodes' => array_values($nodes),
            'links' => $links,
        ];

        return view('admin.partner.network-map.index', compact('mapData'));
    }

    /**
     * @param  array{lat: float, lng: float}  $coords
     * @param  list<array{id: string, code: string, label: string, lat: float, lng: float}>  $agentNodes
     * @return array{id: string, code: string, label: string, distance_km: float}|null
     */
    private function nearestAgentNode(array $coords, array $agentNodes): ?array
    {
        $best = null;
        $bestKm = PHP_FLOAT_MAX;

        foreach ($agentNodes as $agent) {
            $km = $this->haversineKm($coords['lat'], $coords['lng'], $agent['lat'], $agent['lng']);
            if ($km < $bestKm) {
                $bestKm = $km;
                $best = [
                    'id' => $agent['id'],
                    'code' => $agent['code'],
                    'label' => $agent['label'],
                    'distance_km' => round($km, 1),
                ];
            }
        }

        return $best;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthKm * asin(min(1, sqrt($a)));
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function coordsFromCustomer($customer): ?array
    {
        if (! $customer || $customer->lat === null || $customer->long === null) {
            return null;
        }

        $lat = (float) $customer->lat;
        $lng = (float) $customer->long;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }
}
