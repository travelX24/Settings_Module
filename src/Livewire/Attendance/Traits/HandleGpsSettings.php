<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\AttendanceGpsLocation;

trait HandleGpsSettings
{
    public $gpsData = [
        'name' => '',
        'lat' => '',
        'lng' => '',
        'radius' => 100,
        'is_active' => true,
        'address' => '',
        'country' => '',
        'city' => '',
        'region' => ''
    ];

    public function reverseGeocode($lat, $lng)
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'AthkaHR-App',
                'Accept-Language' => app()->getLocale(),
            ])->timeout(8)->retry(1, 300)->get("https://nominatim.openstreetmap.org/reverse", [
                'format' => 'jsonv2',
                'lat' => $lat,
                'lon' => $lng,
                'zoom' => 18,
                'addressdetails' => 1,
                'namedetails' => 1,
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function searchLocation($query, $lat = null, $lng = null, $city = null, $country = null)
    {
        $query = trim((string) $query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $params = [
            'format' => 'jsonv2',
            'q' => $query,
            'limit' => 15,
            'addressdetails' => 1,
            'namedetails' => 1,
            'extratags' => 1,
            'dedupe' => 1,
        ];

        if (is_numeric($lat) && is_numeric($lng)) {
            $lat = (float) $lat;
            $lng = (float) $lng;
            $delta = 0.35;

            // Bias results around the current map center without hiding wider matches.
            $params['viewbox'] = implode(',', [
                $lng - $delta,
                $lat + $delta,
                $lng + $delta,
                $lat - $delta,
            ]);
            $params['bounded'] = 0;
        }

        $city = $this->cleanSearchContext($city);
        $country = $this->cleanSearchContext($country);
        $queries = [$query];

        if ($city || $country) {
            $queries[] = implode(', ', array_filter([$query, $city, $country]));
        }

        if ($country) {
            $queries[] = implode(', ', array_filter([$query, $country]));
        }

        try {
            foreach (array_unique($queries) as $searchQuery) {
                $params['q'] = $searchQuery;
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'User-Agent' => 'AthkaHR-App',
                    'Accept-Language' => app()->getLocale(),
                ])->timeout(8)->retry(1, 300)->get("https://nominatim.openstreetmap.org/search", $params);

                $results = $response->successful() ? ($response->json() ?: []) : [];

                if (!empty($results)) {
                    return $results;
                }
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function cleanSearchContext($value): ?string
    {
        $value = trim((string) $value);

        return in_array($value, ['', '...', '---'], true) ? null : $value;
    }

    public function openGpsModal($id = null)
    {
        $this->authorizeManage();
        $this->resetValidation();
        
        $companyId = auth()->user()->saas_company_id;
        if ($id) {
            $loc = AttendanceGpsLocation::where('saas_company_id', $companyId)->findOrFail($id);
            $this->selectedId = $id;
            $this->isEditing = true;
            $this->gpsData = [
                'name' => $loc->name,
                'lat' => $loc->lat,
                'lng' => $loc->lng,
                'radius' => $loc->radius_meters,
                'is_active' => (bool)$loc->is_active,
                'address' => $loc->address_text ?? '',
                'country' => $loc->country ?? '',
                'city' => $loc->city ?? '',
                'region' => $loc->region ?? ''
            ];
            
            // Set target selections
            if ($loc->branch_id) {
                $this->gpsTarget = 'branch';
                $this->selectedBranch = $loc->branch_id;
            } else {
                $this->gpsTarget = 'groups';
                $this->selectedGroups = [$loc->employee_group_id];
            }
        } else {
            $this->selectedId = null;
            $this->isEditing = false;
            $this->gpsData = [
                'name' => '', 
                'lat' => '', 
                'lng' => '', 
                'radius' => 100, 
                'is_active' => true,
                'address' => '',
                'country' => '',
                'city' => '',
                'region' => ''
            ];
        }
        $this->showGpsModal = true;
    }

    public function validationAttributes()
    {
        return [
            'gpsData.name' => tr('Location Name'),
            'gpsData.lat' => tr('Latitude'),
            'gpsData.lng' => tr('Longitude'),
            'gpsData.radius' => tr('Radius'),
            'selectedBranch' => tr('Branch'),
            'selectedGroups' => tr('Employee Groups'),
        ];
    }

    public function saveGpsLocation()
    {
        $this->authorizeManage();
        
        $rules = [
            'gpsData.name' => 'required|min:3',
            'gpsData.lat' => 'required|numeric',
            'gpsData.lng' => 'required|numeric',
            'gpsData.radius' => 'required|integer|min:10',
        ];

        if ($this->gpsTarget === 'branch') {
            $rules['selectedBranch'] = 'required';
        } else {
            $rules['selectedGroups'] = 'required|array|min:1';
        }

        $this->validate($rules);

        // Map back to model naming based on database schema
        $saveData = [
            'name' => $this->gpsData['name'],
            'lat' => $this->gpsData['lat'],
            'lng' => $this->gpsData['lng'],
            'radius_meters' => $this->gpsData['radius'],
            'address_text' => $this->gpsData['address'],
            'is_active' => $this->gpsData['is_active'],
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->gpsTarget === 'branch') {
            $saveData['branch_id'] = ($this->selectedBranch === 'main') ? null : $this->selectedBranch;
            $saveData['employee_group_id'] = null;
        } else {
            // Note: DB schema shows singular employee_group_id. 
            // Taking the first one if multiple are selected, or null if none.
            $saveData['employee_group_id'] = is_array($this->selectedGroups) ? ($this->selectedGroups[0] ?? null) : $this->selectedGroups;
            $saveData['branch_id'] = null;
        }

        $this->attendanceSettingService->saveGpsLocation(
            auth()->user()->saas_company_id,
            $saveData,
            $this->selectedId
        );

        $this->showGpsModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('GPS location saved successfully.'));
    }

    public function deleteGpsLocation($id)
    {
        $this->authorizeManage();
        $companyId = auth()->user()->saas_company_id;
        AttendanceGpsLocation::where('saas_company_id', $companyId)->where('id', $id)->delete();
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('GPS location deleted.'));
    }
}
