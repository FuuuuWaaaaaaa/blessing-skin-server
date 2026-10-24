<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SetAppLocale
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle($event)
    {
        /** @var User */
        $user = $event->user;

        if ($this->request->has('lang')) {
            $locale = $this->request->input('lang');
            $info = is_string($locale) ? Arr::get(config('locales'), $locale) : null;
            if (is_array($info) && ($locale = Arr::get($info, 'alias', $locale))) {
                $user->locale = $locale;
                $user->save();
            }

            return;
        }

        $locale = $user->locale;
        if (is_string($locale) && Arr::has(config('locales'), $locale)) {
            app()->setLocale($locale);
        }
    }
}
