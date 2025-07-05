<?php

namespace Samandaruzbekistan\UzRegionsPackage\models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'regions';

    protected $fillable = [
        'id', 'name_uz', 'name_oz', 'name_ru'
    ];

    /**
     * Get the districts associated with the region.
     */
    public function districts()
    {
        return $this->hasMany(District::class, 'region_id', 'id');
    }

    /**
     * Get the villages associated with the region.
     */
    public function villages()
    {
        return $this->hasManyThrough(Village::class, District::class, 'region_id', 'district_id', 'id', 'id');
    }

    /**
     * Get all regions for inline keyboard
     */
    public static function getAllForKeyboard()
    {
        return self::select('id', 'name_uz')
            ->orderBy('name_uz')
            ->get()
            ->map(function ($region) {
                return [
                    'text' => $region->name_uz,
                    'callback_data' => 'region_' . $region->id
                ];
            });
    }

    /**
     * Format regions for inline keyboard (2 columns)
     */
    public static function getFormattedForKeyboard()
    {
        $regions = self::getAllForKeyboard();
        $keyboard = [];
        $row = [];

        foreach ($regions as $region) {
            $row[] = $region;

            if (count($row) == 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }

        // Add remaining region if odd number
        if (!empty($row)) {
            $keyboard[] = $row;
        }

        return $keyboard;
    }
}
