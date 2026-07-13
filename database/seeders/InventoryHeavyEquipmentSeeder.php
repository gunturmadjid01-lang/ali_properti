<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryHeavyEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Elektronik','Furniture','Peralatan Kantor','Peralatan Konstruksi','Peralatan Tukang','Alat Kebersihan','Peralatan Keamanan','Alat Ukur'] as $name) {
            DB::table('inventory_categories')->updateOrInsert(['name'=>$name], ['description'=>'Kategori inventaris perusahaan','is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
        }
        foreach ([['LOC-GUDANG','Gudang Utama','warehouse'],['LOC-KANTOR','Kantor','office'],['LOC-MARKETING','Kantor Marketing','office'],['LOC-SECURITY','Pos Security','post']] as [$code,$name,$type]) {
            DB::table('inventory_locations')->updateOrInsert(['code'=>$code], ['name'=>$name,'type'=>$type,'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
        }
        foreach (['Excavator','Dump Truck','Bulldozer','Wheel Loader','Crane','Vibro Roller','Forklift','Motor Grader','Concrete Mixer'] as $name) {
            DB::table('heavy_equipment_types')->updateOrInsert(['name'=>$name], ['description'=>'Jenis alat berat','is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
        }
    }
}
