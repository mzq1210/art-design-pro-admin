<?php

declare(strict_types=1);

namespace console\controllers;

use common\models\Menu;
use yii\console\Controller;
use yii\helpers\Console;

class MenuSeedController extends Controller
{
    public function actionIndex(): int
    {
        $dashboard = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '仪表盘',
            'name' => 'Dashboard',
            'path' => '/dashboard',
            'component' => '/index/index',
            'icon' => 'ri:pie-chart-line',
            'permission' => '',
            'sort' => 1,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $this->upsertMenu([
            'parent_id' => $dashboard->id,
            'type' => 2,
            'title' => '工作台',
            'name' => 'Console',
            'path' => 'console',
            'component' => '/dashboard/console',
            'icon' => '',
            'permission' => '',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 0,
        ]);

        $system = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '系统管理',
            'name' => 'System',
            'path' => '/system',
            'component' => '/index/index',
            'icon' => 'ri:user-3-line',
            'permission' => '',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $user = $this->upsertMenu([
            'parent_id' => $system->id,
            'type' => 2,
            'title' => '用户管理',
            'name' => 'User',
            'path' => 'user',
            'component' => '/system/user',
            'icon' => '',
            'permission' => 'user.view',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($user->id, [
            ['新增用户', 'user.create', 10],
            ['修改用户', 'user.update', 20],
            ['删除用户', 'user.delete', 30],
            ['查看用户角色', 'user.role.view', 40],
            ['分配用户角色', 'user.role.assign', 50],
        ]);

        $role = $this->upsertMenu([
            'parent_id' => $system->id,
            'type' => 2,
            'title' => '角色管理',
            'name' => 'Role',
            'path' => 'role',
            'component' => '/system/role',
            'icon' => '',
            'permission' => 'role.view',
            'sort' => 20,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($role->id, [
            ['新增角色', 'role.create', 10],
            ['修改角色', 'role.update', 20],
            ['删除角色', 'role.delete', 30],
            ['查看角色权限', 'role.permission.view', 40],
            ['分配角色权限', 'role.permission.assign', 50],
        ]);

        $menu = $this->upsertMenu([
            'parent_id' => $system->id,
            'type' => 2,
            'title' => '菜单管理',
            'name' => 'Menus',
            'path' => 'menu',
            'component' => '/system/menu',
            'icon' => '',
            'permission' => 'menu.view',
            'sort' => 30,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($menu->id, [
            ['新增菜单', 'menu.create', 10],
            ['修改菜单', 'menu.update', 20],
            ['删除菜单', 'menu.delete', 30],
        ]);

        $permission = $this->upsertMenu([
            'parent_id' => $system->id,
            'type' => 2,
            'title' => '权限管理',
            'name' => 'Permission',
            'path' => 'permission',
            'component' => '/system/permission',
            'icon' => '',
            'permission' => 'permission.view',
            'sort' => 40,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($permission->id, [
            ['新增权限', 'permission.create', 10],
            ['修改权限', 'permission.update', 20],
            ['删除权限', 'permission.delete', 30],
        ]);

        $dict = $this->upsertMenu([
            'parent_id' => $system->id,
            'type' => 2,
            'title' => '字典管理',
            'name' => 'Dict',
            'path' => 'dict',
            'component' => '/system/dict',
            'icon' => '',
            'permission' => 'dict.view',
            'sort' => 50,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($dict->id, [
            ['新增字典', 'dict.create', 10],
            ['修改字典', 'dict.update', 20],
            ['删除字典', 'dict.delete', 30],
        ]);

        $file = $this->upsertMenu([
            'parent_id' => $system->id,
            'type' => 2,
            'title' => '文件管理',
            'name' => 'File',
            'path' => 'file',
            'component' => '/system/file',
            'icon' => '',
            'permission' => 'file.view',
            'sort' => 60,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($file->id, [
            ['上传文件', 'file.upload', 10],
            ['修改文件', 'file.update', 20],
            ['删除文件', 'file.delete', 30],
            ['新增文件分组', 'file.group.create', 40],
            ['修改文件分组', 'file.group.update', 50],
            ['删除文件分组', 'file.group.delete', 60],
        ]);

        $operationLog = $this->upsertMenu([
            'parent_id' => $system->id,
            'type' => 2,
            'title' => '操作日志',
            'name' => 'OperationLog',
            'path' => 'operation-log',
            'component' => '/system/operation-log',
            'icon' => '',
            'permission' => 'operation-log.view',
            'sort' => 70,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($operationLog->id, [
            ['删除操作日志', 'operation-log.delete', 10],
        ]);

        $queue = $this->upsertMenu([
            'parent_id' => $system->id,
            'type' => 2,
            'title' => '队列任务',
            'name' => 'QueueTask',
            'path' => 'queue-task',
            'component' => '/system/queue-task',
            'icon' => '',
            'permission' => 'queue.view',
            'sort' => 80,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($queue->id, [
            ['创建队列任务', 'queue.create', 10],
            ['删除队列任务', 'queue.delete', 20],
        ]);

        $dingtalk = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '钉钉',
            'name' => 'DingTalk',
            'path' => '/dingtalk',
            'component' => '/index/index',
            'icon' => 'ri:dingding-line',
            'permission' => '',
            'sort' => 15,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $employee = $this->upsertMenu([
            'parent_id' => $dingtalk->id,
            'type' => 2,
            'title' => '员工管理',
            'name' => 'DingTalkEmployee',
            'path' => 'employee',
            'component' => '/dingtalk/employee',
            'icon' => '',
            'permission' => 'dingtalk.employee.view',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($employee->id, [
            ['同步员工', 'dingtalk.employee.sync', 10],
        ]);

        $customer = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '客户',
            'name' => 'Customer',
            'path' => '/customer',
            'component' => '/index/index',
            'icon' => 'ri:customer-service-2-line',
            'permission' => '',
            'sort' => 18,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $customerManage = $this->upsertMenu([
            'parent_id' => $customer->id,
            'type' => 2,
            'title' => '客户管理',
            'name' => 'CustomerManage',
            'path' => 'manage',
            'component' => '/customer/manage',
            'icon' => '',
            'permission' => 'customer.view',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($customerManage->id, [
            ['新增客户', 'customer.create', 10],
            ['修改客户', 'customer.update', 20],
            ['删除客户', 'customer.delete', 30],
            ['新增联系人', 'customer.contact.create', 40],
            ['修改联系人', 'customer.contact.update', 50],
            ['删除联系人', 'customer.contact.delete', 60],
        ]);

        $product = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '产品',
            'name' => 'Product',
            'path' => '/product',
            'component' => '/index/index',
            'icon' => 'ri:shopping-bag-3-line',
            'permission' => '',
            'sort' => 19,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $productManage = $this->upsertMenu([
            'parent_id' => $product->id,
            'type' => 2,
            'title' => '产品管理',
            'name' => 'ProductManage',
            'path' => 'manage',
            'component' => '/product/manage',
            'icon' => '',
            'permission' => 'product.view',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($productManage->id, [
            ['新增产品', 'product.create', 10],
            ['修改产品', 'product.update', 20],
            ['删除产品', 'product.delete', 30],
        ]);

        $productCategory = $this->upsertMenu([
            'parent_id' => $product->id,
            'type' => 2,
            'title' => '产品分类',
            'name' => 'ProductCategory',
            'path' => 'category',
            'component' => '/product/category',
            'icon' => '',
            'permission' => 'product.category.view',
            'sort' => 20,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($productCategory->id, [
            ['新增产品分类', 'product.category.create', 10],
            ['修改产品分类', 'product.category.update', 20],
            ['删除产品分类', 'product.category.delete', 30],
        ]);

        $manuscript = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '稿件',
            'name' => 'Manuscript',
            'path' => '/manuscript',
            'component' => '/index/index',
            'icon' => 'ri:article-line',
            'permission' => '',
            'sort' => 21,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $manuscriptManage = $this->upsertMenu([
            'parent_id' => $manuscript->id,
            'type' => 2,
            'title' => '稿件管理',
            'name' => 'ManuscriptManage',
            'path' => 'manage',
            'component' => '/manuscript/manage',
            'icon' => '',
            'permission' => 'manuscript.view',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($manuscriptManage->id, [
            ['新增稿件', 'manuscript.create', 10],
            ['修改稿件', 'manuscript.update', 20],
            ['删除稿件', 'manuscript.delete', 30],
        ]);

        $fulfillment = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '履约',
            'name' => 'Fulfillment',
            'path' => '/fulfillment',
            'component' => '/index/index',
            'icon' => 'ri:calendar-check-line',
            'permission' => '',
            'sort' => 22,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $fulfillmentManage = $this->upsertMenu([
            'parent_id' => $fulfillment->id,
            'type' => 2,
            'title' => '履约管理',
            'name' => 'FulfillmentManage',
            'path' => 'manage',
            'component' => '/fulfillment/manage',
            'icon' => '',
            'permission' => 'fulfillment.view',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($fulfillmentManage->id, [
            ['新增履约', 'fulfillment.create', 10],
            ['修改履约', 'fulfillment.update', 20],
            ['删除履约', 'fulfillment.delete', 30],
            ['登记执行', 'fulfillment.execute', 40],
        ]);

        $contract = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '合同',
            'name' => 'Contract',
            'path' => '/contract',
            'component' => '/index/index',
            'icon' => 'ri:file-paper-2-line',
            'permission' => '',
            'sort' => 23,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $contractManage = $this->upsertMenu([
            'parent_id' => $contract->id,
            'type' => 2,
            'title' => '合同管理',
            'name' => 'ContractManage',
            'path' => 'manage',
            'component' => '/contract/manage',
            'icon' => '',
            'permission' => 'contract.view',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($contractManage->id, [
            ['新增合同', 'contract.create', 10],
            ['修改合同', 'contract.update', 20],
            ['删除合同', 'contract.delete', 30],
        ]);

        $receivable = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '回款',
            'name' => 'Receivable',
            'path' => '/receivable',
            'component' => '/index/index',
            'icon' => 'ri:money-cny-circle-line',
            'permission' => '',
            'sort' => 24,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $receivablePlan = $this->upsertMenu([
            'parent_id' => $receivable->id,
            'type' => 2,
            'title' => '回款计划',
            'name' => 'ReceivablePlan',
            'path' => 'plan',
            'component' => '/receivable/plan',
            'icon' => '',
            'permission' => 'receivable.plan.view',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($receivablePlan->id, [
            ['新增回款计划', 'receivable.plan.create', 10],
            ['修改回款计划', 'receivable.plan.update', 20],
            ['删除回款计划', 'receivable.plan.delete', 30],
            ['登记回款', 'receivable.record.create', 40],
        ]);

        $receivableRecord = $this->upsertMenu([
            'parent_id' => $receivable->id,
            'type' => 2,
            'title' => '回款记录',
            'name' => 'ReceivableRecord',
            'path' => 'record',
            'component' => '/receivable/record',
            'icon' => '',
            'permission' => 'receivable.record.view',
            'sort' => 20,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($receivableRecord->id, [
            ['新增回款记录', 'receivable.record.create', 10],
            ['修改回款记录', 'receivable.record.update', 20],
            ['删除回款记录', 'receivable.record.delete', 30],
        ]);

        $content = $this->upsertMenu([
            'parent_id' => 0,
            'type' => 1,
            'title' => '内容管理',
            'name' => 'Content',
            'path' => '/content',
            'component' => '/index/index',
            'icon' => 'ri:file-list-3-line',
            'permission' => '',
            'sort' => 20,
            'visible' => 1,
            'keep_alive' => 1,
        ]);

        $notice = $this->upsertMenu([
            'parent_id' => $content->id,
            'type' => 2,
            'title' => '公告管理',
            'name' => 'Notice',
            'path' => 'notice',
            'component' => '/content/notice',
            'icon' => '',
            'permission' => 'notice.view',
            'sort' => 10,
            'visible' => 1,
            'keep_alive' => 1,
        ]);
        $this->upsertButtons($notice->id, [
            ['新增公告', 'notice.create', 10],
            ['修改公告', 'notice.update', 20],
            ['删除公告', 'notice.delete', 30],
        ]);

        $count = Menu::find()->count();
        $this->invalidateMenuCache();
        $this->stdout("Menu seed completed. Total menus: {$count}\n", Console::FG_GREEN);

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * @param array{0:string,1:string,2:int}[] $buttons
     */
    private function upsertButtons(int $parentId, array $buttons): void
    {
        foreach ($buttons as [$title, $permission, $sort]) {
            $this->upsertMenu([
                'parent_id' => $parentId,
                'type' => 3,
                'title' => $title,
                'name' => '',
                'path' => '',
                'component' => '',
                'icon' => '',
                'permission' => $permission,
                'sort' => $sort,
                'visible' => 0,
                'keep_alive' => 0,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function upsertMenu(array $attributes): Menu
    {
        $menu = $this->findMenu($attributes) ?? new Menu();
        $now = time();

        $menu->setAttributes($attributes);
        $menu->is_external = $attributes['is_external'] ?? 0;
        $menu->external_url = $attributes['external_url'] ?? '';
        $menu->remark = $attributes['remark'] ?? '';
        $menu->created_at = $menu->created_at ?: $now;
        $menu->updated_at = $now;

        if (!$menu->save()) {
            $errors = json_encode($menu->getFirstErrors(), JSON_UNESCAPED_UNICODE);
            throw new \RuntimeException("Save menu failed: {$errors}");
        }

        return $menu;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function findMenu(array $attributes): ?Menu
    {
        if (!empty($attributes['permission'])) {
            return Menu::find()
                ->where(['permission' => $attributes['permission']])
                ->one();
        }

        if (!empty($attributes['name'])) {
            return Menu::find()
                ->where(['name' => $attributes['name'], 'path' => $attributes['path'] ?? ''])
                ->one();
        }

        return Menu::find()
            ->where([
                'parent_id' => $attributes['parent_id'] ?? 0,
                'type' => $attributes['type'] ?? 1,
                'title' => $attributes['title'] ?? '',
            ])
            ->one();
    }

    private function invalidateMenuCache(): void
    {
        try {
            \Yii::$app->menuCache->set('version', time());
        } catch (\Throwable) {
        }
    }
}
