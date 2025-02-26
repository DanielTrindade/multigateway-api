<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = [
            [
                'name' => 'João Silva',
                'email' => 'joao.silva@example.com',
            ],
            [
                'name' => 'Maria Souza',
                'email' => 'maria.souza@example.com',
            ],
            [
                'name' => 'Pedro Oliveira',
                'email' => 'pedro.oliveira@example.com',
            ],
            [
                'name' => 'Ana Santos',
                'email' => 'ana.santos@example.com',
            ],
            [
                'name' => 'Carlos Ferreira',
                'email' => 'carlos.ferreira@example.com',
            ],
            [
                'name' => 'Juliana Costa',
                'email' => 'juliana.costa@example.com',
            ],
            [
                'name' => 'Roberto Almeida',
                'email' => 'roberto.almeida@example.com',
            ],
            [
                'name' => 'Fernanda Lima',
                'email' => 'fernanda.lima@example.com',
            ],
            [
                'name' => 'Luciana Martins',
                'email' => 'luciana.martins@example.com',
            ],
            [
                'name' => 'Marcelo Rodrigues',
                'email' => 'marcelo.rodrigues@example.com',
            ],
        ];

        foreach ($clients as $clientData) {
            Client::create($clientData);
        }
    }
}
