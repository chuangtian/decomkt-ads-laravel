<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExternalDataController extends Controller
{
    public function refresh(Request $request, ModuleController $modules, BrandController $brand): RedirectResponse
    {
        $data = $request->validate(['path' => ['required', 'string']]);
        $count = 0;
        if ($data['path'] === '/workspace/brand') $count = count($brand->sync());
        else {
            $result = $modules->syncExternalPage($data['path']);
            $count = count($result['rows'] ?? []);
        }

        return back()->with('success', "数据已更新，共 {$count} 条记录。");
    }
}
