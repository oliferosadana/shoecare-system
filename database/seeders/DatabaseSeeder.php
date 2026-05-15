<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'role' => 'staff',
            'phone' => '081200000001',
            'is_active' => true,
            'password' => 'password',
        ]);

        User::updateOrCreate([
            'email' => 'admin@zolix.id',
        ], [
            'name' => 'Admin ZOLIX',
            'role' => 'admin',
            'phone' => '081200000000',
            'is_active' => true,
            'password' => 'password123',
        ]);

        DB::table('services')->upsert([
            [
                'name' => 'Deep Clean',
                'slug' => 'deep-clean',
                'description' => 'Pembersihan mendalam untuk upper, midsole, dan outsole.',
                'price' => 70000,
                'estimated_hours' => 48,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fast Clean',
                'slug' => 'fast-clean',
                'description' => 'Perawatan cepat untuk penggunaan harian.',
                'price' => 45000,
                'estimated_hours' => 24,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Unyellowing',
                'slug' => 'unyellowing',
                'description' => 'Treatment untuk mengurangi warna kuning pada sole.',
                'price' => 85000,
                'estimated_hours' => 72,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Repair',
                'slug' => 'repair',
                'description' => 'Perbaikan ringan untuk bagian sepatu yang rusak.',
                'price' => 30000,
                'estimated_hours' => 72,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Repaint',
                'slug' => 'repaint',
                'description' => 'Pewarnaan ulang untuk mengembalikan tampilan sepatu.',
                'price' => 50000,
                'estimated_hours' => 96,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['slug'], ['name', 'description', 'price', 'estimated_hours', 'is_active', 'updated_at']);
    }
}
