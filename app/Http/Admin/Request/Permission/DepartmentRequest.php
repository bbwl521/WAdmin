<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

namespace App\Http\Admin\Request\Permission;

use App\Http\Common\Request\Traits\HttpMethodTrait;
use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use App\Schema\DepartmentSchema;
use Hyperf\Validation\Request\FormRequest;

#[\Mine\Swagger\Attributes\FormRequest(
    schema: DepartmentSchema::class,
    only: [
        'name', 'parent_id',
    ]
)]
class DepartmentRequest extends FormRequest
{
    use HttpMethodTrait;
    use NoAuthorizeTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:60',
            'parent_id' => 'sometimes|integer',
        ];
        if ($this->isCreate()) {
            $rules['name'] = 'required|string|max:60|unique:department,name';
        }
        if ($this->isUpdate()) {
            $rules['name'] = 'required|string|max:60|unique:department,name,' . $this->route('id');
        }
        // 关联数据验证 - 确保是整数数组
        $rules['department_users'] = 'sometimes|array';
        $rules['department_users.*'] = 'sometimes|integer';
        $rules['leader'] = 'sometimes|array';
        $rules['leader.*'] = 'sometimes|integer|min:1';
        return $rules;
    }

    public function attributes(): array
    {
        return [
            'name' => '部门名称',
            'parent_id' => '上级部门',
        ];
    }
}
