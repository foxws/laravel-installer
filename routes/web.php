<?php

declare(strict_types=1);

use App\Livewire\ChooseSource;
use App\Livewire\Installer;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Installer::class)->name('installer');
Route::livewire('/source', ChooseSource::class)->name('source');
