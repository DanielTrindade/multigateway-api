<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run()
	{
		// Criar produtos de exemplo
		Product::create([
			'name' => 'Produto 1',
			'amount' => 1000, // R$ 10,00
		]);

		Product::create([
			'name' => 'Produto 2',
			'amount' => 2500, // R$ 25,00
		]);

		Product::create([
			'name' => 'Produto Premium',
			'amount' => 9990, // R$ 99,90
		]);
	}
}
