<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Users sign in with a username. Existing users get one derived from their email (admin@x.test -> admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 50)->nullable()->after('name');
        });

        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name', 'email']) as $u) {
            $base = Str::of(Str::before($u->email, '@') ?: $u->name)->lower()->replaceMatches('/[^a-z0-9._-]/', '')->limit(40, '')->value() ?: 'user';
            $name = $base;
            for ($i = 2; in_array($name, $taken, true); $i++) {
                $name = $base.$i;
            }
            $taken[] = $name;
            DB::table('users')->where('id', $u->id)->update(['username' => $name]);
        }

        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 50)->nullable(false)->change();
            $t->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['username']);
            $t->dropColumn('username');
        });
    }
};
