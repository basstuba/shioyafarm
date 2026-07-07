<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $param = [
            'last_name' => '仮野',
            'first_name' => '太郎',
            'prefecture' => '北海道',
            'city' => '札幌市中央区北5条西4丁目',
            'tell' => '09012345678',
            'email' => 'test@test.com',
            'password' => Hash::make('test1234'),
        ];
        DB::table('users')->insert($param);
    }
}
