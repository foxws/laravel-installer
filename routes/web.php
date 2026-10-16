<?php

declare(strict_types=1);

use App\Livewire\Installer;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Installer::class)->name('installer');
