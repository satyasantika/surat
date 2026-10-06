<?php

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(PHPUnit\Framework\TestCase::class)->in('Unit');
