<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PreferencesController extends Controller
{
    public function setLocale(Request $request, string $locale)
    {
        if (!in_array($locale, ['ar', 'en'], true)) {
            abort(404);
        }
        $request->session()->put('locale', $locale);
        return back();
    }

    public function setTheme(Request $request, string $theme)
    {
        if (!in_array($theme, ['light', 'dark'], true)) {
            abort(404);
        }
        $request->session()->put('theme', $theme);
        return back();
    }
}
