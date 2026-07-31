<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('refresh-tokens:prune')->daily();
