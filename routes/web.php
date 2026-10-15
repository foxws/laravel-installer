<?php

use App\Livewire\Installer;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Installer::class)->name('installer');
