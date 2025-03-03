<?php

namespace Database\Seeders;

use App\Models\Gateway;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Configurar gateways iniciais
        Gateway::create([
            'name' => 'Gateway 1',
            'type' => 'gateway1',
            'is_active' => true,
            'priority' => 1,
            'credentials' => [
                'email' => 'dev@betalent.tech',
                'token' => 'FEC9BB078BF338F464F96B48089EB498',
            ],
        ]);

        Gateway::create([
            'name' => 'Gateway 2',
            'type' => 'gateway2',
            'is_active' => true,
            'priority' => 2,
            'credentials' => [
                'auth_token' => 'tk_f2198cc671b5289fa856',
                'auth_secret' => '3d15e8ed6131446ea7e3456728b1211f',
            ],
        ]);
    }
}
