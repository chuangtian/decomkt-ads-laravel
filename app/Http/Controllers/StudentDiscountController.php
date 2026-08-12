<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentDiscountController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        DB::table('StudentDiscountCampaign')->where('storeId', 'default-store')->update(['enabled' => $data['enabled'], 'updatedAt' => now()]);

        return back()->with('success', $data['enabled'] ? '学生优惠已启用' : '学生优惠已停用');
    }
}
