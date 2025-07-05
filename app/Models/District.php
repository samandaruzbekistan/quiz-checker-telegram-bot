<?php

namespace Samandaruzbekistan\UzRegionsPackage\models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $table = 'districts'; // Jadval nomi

    protected $fillable = [
        'id', 'region_id', 'name_uz', 'name_oz', 'name_ru'
    ];

    /**
     * Get the villages associated with the district.
     */
    public function villages()
    {
        return $this->hasMany(Village::class, 'district_id', 'id');
    }

    /**
     * Get the region associated with the district.
     */
    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    /**
     * Get districts for a specific region for inline keyboard
     */
    public static function getForRegionForKeyboard($region_id)
    {
        return self::where('region_id', $region_id)
            ->select('id', 'name_uz')
            ->orderBy('name_uz')
            ->get()
            ->map(function ($district) {
                return [
                    'text' => $district->name_uz,
                    'callback_data' => 'district_' . $district->id
                ];
            });
    }

    /**
     * Format districts for inline keyboard (2 columns)
     */
    public static function getFormattedForKeyboard($region_id)
    {
        $districts = self::getForRegionForKeyboard($region_id);
        $keyboard = [];
        $row = [];

        foreach ($districts as $district) {
            $row[] = $district;

            if (count($row) == 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }

        // Add remaining district if odd number
        if (!empty($row)) {
            $keyboard[] = $row;
        }

        return $keyboard;
    }
}
