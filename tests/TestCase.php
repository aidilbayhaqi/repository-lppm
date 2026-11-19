<?php

namespace Tests;

use App\Models\User;
use App\Support\SessionState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);
        if ($user instanceof User) {
            $this->withSession(SessionState::attributes($user));
        }

        return $this;
    }
}
