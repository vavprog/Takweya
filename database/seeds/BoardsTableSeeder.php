<?php

use Illuminate\Database\Seeder;

class BoardsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('boards')->insert([
            [
                'board_id' => '5bb5e7c185d33c4600f0947b',
                'link' => 'uakcsytmb'
            ],
        ]);
    }
}
