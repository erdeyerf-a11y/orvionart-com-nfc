<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('orvionart:about', function () {
    $this->info('ORVIONART '.trim((string) file_get_contents(base_path('VERSION'))));
    $this->line('Bu komut kurulumun veya bütün modüllerin tamamlandığını göstermez.');
})->purpose('ORVIONART sürüm bilgisini gösterir');
