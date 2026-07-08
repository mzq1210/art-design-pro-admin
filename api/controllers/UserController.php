<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\Menu;
use common\models\User;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class UserController extends BaseController
{
    protected array $authOnlyActions = [
        'profile',
        'update-profile',
        'change-password',
        'menus',
    ];

    protected array $rbacPermissions = [
        'index' => 'user.view',
        'view' => 'user.view',
        'create' => 'user.create',
        'update' => 'user.update',
        'delete' => 'user.delete',
        'roles' => 'user.role.view',
        'assign-roles' => 'user.role.assign',
    ];

    public function actionIndex(): array
    {
        $page     = max(1, (int)Yii::$app->request->post('page', 1));
        $size     = max(1, (int)Yii::$app->request->post('size', 10));
        $username = trim((string)Yii::$app->request->post('username', ''));
        $email    = trim((string)Yii::$app->request->post('email', ''));
        $status   = Yii::$app->request->post('status', '');

        $query = User::find();

        if ($username !== '') {
            $query->andWhere(['like', 'username', $username]);
        }

        if ($email !== '') {
            $query->andWhere(['like', 'email', $email]);
        }

        if ($status !== '' && $status !== null) {
            $query->andWhere(['status' => (int)$status]);
        }

        $total = (int)(clone $query)->count();
        $users = $query
            ->orderBy(['id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->all();

        $records = [];
        foreach ($users as $user) {
            $records[] = $this->serializeUser($user);
        }

        return [
            'records' => $records,
            'current' => $page,
            'size'    => $size,
            'total'   => $total,
        ];
    }

    public function actionProfile(): array
    {
        $user = Yii::$app->user->identity;
        $permissions = Yii::$app->authManager->getPermissionsByUser((int)$user->id);

        $buttons = array_keys($permissions);
        return [
            'userId'       => (int)$user->id,
            'userName'     => $user->username,
            'realName'     => $user->real_name ?: $user->username,
            'nickName'     => $user->real_name ?: $user->username,
            'email'        => $user->email,
            'avatar'       => $user->avatar ?: '',
            'mobile'       => $user->mobile ?: '',
            'address'      => '',
            'gender'       => '',
            'introduction' => '',
            'buttons'      => $buttons,
            'roles'        => array_keys(Yii::$app->authManager->getRolesByUser((int)$user->id)),
        ];
    }

    public function actionUpdateProfile(): array
    {
        $user = $this->findUser((int)Yii::$app->user->id);
        $realName = trim((string)Yii::$app->request->post('userName', $user->real_name ?: $user->username));
        $email = trim((string)Yii::$app->request->post('email', $user->email));
        $avatar = trim((string)Yii::$app->request->post('avatar', (string)$user->avatar));

        $this->validateUserInput($user->username, $email, '', (int)$user->id, false);

        $user->real_name = $realName;
        $user->email = $email;
        $user->avatar = $avatar;

        if (!$user->save(false)) {
            throw new BadRequestHttpException('Failed to update profile.');
        }

        return $this->actionProfile();
    }

    public function actionChangePassword(): array
    {
        $user = $this->findUser((int)Yii::$app->user->id);
        $oldPassword = (string)Yii::$app->request->post('oldPassword', '');
        $newPassword = (string)Yii::$app->request->post('newPassword', '');

        if ($oldPassword === '' || $newPassword === '') {
            throw new BadRequestHttpException('Password is required.');
        }

        if (!$user->validatePassword($oldPassword)) {
            throw new BadRequestHttpException('Current password is incorrect.');
        }

        if (strlen($newPassword) < 6) {
            throw new BadRequestHttpException('Password must be at least 6 characters.');
        }

        $user->setPassword($newPassword);
        if (!$user->save(false)) {
            throw new BadRequestHttpException('Failed to change password.');
        }

        return [
            'changed' => true,
        ];
    }

    public function actionMenus(): array
    {
        $version = $this->getMenuCacheVersion();
        $cacheKey = 'user:' . (int)Yii::$app->user->id . ':menus:' . $version;
        $cachedMenus = $this->getMenuCache($cacheKey);
        if (is_array($cachedMenus)) {
            return $cachedMenus;
        }

        $menus = Menu::find()
            ->where(['in', 'type', [1, 2, 3]])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $routes = $this->appendUserCenterRoute($this->buildRouteMenus($menus));
        $this->setMenuCache($cacheKey, $routes);

        return $routes;
    }

    public function actionView(): array
    {
        return $this->serializeUser($this->findUser((int)Yii::$app->request->post('id', 0)));
    }

    public function actionCreate(): array
    {
        $username = trim((string)Yii::$app->request->post('username', ''));
        $email    = trim((string)Yii::$app->request->post('email', ''));
        $password = (string)Yii::$app->request->post('password', '');
        $status   = (int)Yii::$app->request->post('status', User::STATUS_ACTIVE);
        $roles    = Yii::$app->request->post('roles', []);
        $avatar   = trim((string)Yii::$app->request->post('avatar', ''));

        $this->validateUserInput($username, $email, $password, null, true);
        $this->validateStatus($status);

        if (!is_array($roles)) {
            throw new BadRequestHttpException('Roles must be an array.');
        }

        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->avatar = $avatar;
        $user->status = $status;
        $user->setPassword($password);
        $user->generateAuthKey();

        $transaction = Yii::$app->db->beginTransaction();

        try {
            if (!$user->save(false)) {
                throw new BadRequestHttpException('Failed to create user.');
            }

            $this->syncRoles((int)$user->id, $roles);
            $transaction->commit();
            $this->invalidateMenuCache();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $this->serializeUser($user);
    }

    public function actionUpdate(): array
    {
        $user     = $this->findUser((int)Yii::$app->request->post('id', 0));
        $username = trim((string)Yii::$app->request->post('username', $user->username));
        $email    = trim((string)Yii::$app->request->post('email', $user->email));
        $password = (string)Yii::$app->request->post('password', '');
        $status   = (int)Yii::$app->request->post('status', $user->status);
        $avatar   = trim((string)Yii::$app->request->post('avatar', (string)$user->avatar));

        $this->validateUserInput($username, $email, $password, (int)$user->id, false);
        $this->validateStatus($status);

        $user->username = $username;
        $user->email = $email;
        $user->avatar = $avatar;
        $user->status = $status;

        if ($password !== '') {
            $user->setPassword($password);
        }

        if (!$user->save(false)) {
            throw new BadRequestHttpException('Failed to update user.');
        }

        return $this->serializeUser($user);
    }

    public function actionDelete(): array
    {
        $user = $this->findUser((int)Yii::$app->request->post('id', 0));

        if ((int)$user->id === (int)Yii::$app->user->id) {
            throw new BadRequestHttpException('Cannot delete current login user.');
        }

        $user->status = User::STATUS_DELETED;

        if (!$user->save(false)) {
            throw new BadRequestHttpException('Failed to delete user.');
        }

        return [
            'id'      => (int)$user->id,
            'deleted' => true,
        ];
    }

    public function actionRoles(): array
    {
        $user = $this->findUser((int)Yii::$app->request->post('id', 0));

        return [
            'id'    => (int)$user->id,
            'roles' => array_keys(Yii::$app->authManager->getRolesByUser((int)$user->id)),
        ];
    }

    public function actionAssignRoles(): array
    {
        $user  = $this->findUser((int)Yii::$app->request->post('id', 0));
        $roles = Yii::$app->request->post('roles', []);

        if (!is_array($roles)) {
            throw new BadRequestHttpException('Roles must be an array.');
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $this->syncRoles((int)$user->id, $roles);
            $transaction->commit();
            $this->invalidateMenuCache();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return [
            'assigned' => true,
        ];
    }

    private function findUser(int $id): User
    {
        if ($id <= 0) {
            throw new BadRequestHttpException('User id is required.');
        }

        $user = User::findOne($id);
        if ($user === null) {
            throw new NotFoundHttpException('User does not exist.');
        }

        return $user;
    }

    private function validateUserInput(
        string $username,
        string $email,
        string $password,
        ?int $ignoreId,
        bool $passwordRequired
    ): void {
        if ($username === '') {
            throw new BadRequestHttpException('Username is required.');
        }

        if ($email === '') {
            throw new BadRequestHttpException('Email is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Email format is invalid.');
        }

        if ($passwordRequired && $password === '') {
            throw new BadRequestHttpException('Password is required.');
        }

        if ($password !== '' && strlen($password) < 6) {
            throw new BadRequestHttpException('Password must be at least 6 characters.');
        }

        $usernameQuery = User::find()->where(['username' => $username]);
        $emailQuery = User::find()->where(['email' => $email]);

        if ($ignoreId !== null) {
            $usernameQuery->andWhere(['<>', 'id', $ignoreId]);
            $emailQuery->andWhere(['<>', 'id', $ignoreId]);
        }

        if ($usernameQuery->exists()) {
            throw new BadRequestHttpException('Username already exists.');
        }

        if ($emailQuery->exists()) {
            throw new BadRequestHttpException('Email already exists.');
        }
    }

    private function validateStatus(int $status): void
    {
        if (!in_array($status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_DELETED], true)) {
            throw new BadRequestHttpException('User status is invalid.');
        }
    }

    private function syncRoles(int $userId, array $roleNames): void
    {
        $auth = Yii::$app->authManager;
        $roles = [];

        foreach (array_unique($roleNames) as $roleName) {
            $roleName = trim((string)$roleName);
            if ($roleName === '') {
                continue;
            }

            $role = $auth->getRole($roleName);
            if ($role === null) {
                throw new BadRequestHttpException("Role {$roleName} does not exist.");
            }

            $roles[] = $role;
        }

        $auth->revokeAll($userId);

        foreach ($roles as $role) {
            $auth->assign($role, $userId);
        }
    }

    /**
     * @param Menu[] $menus
     */
    private function buildRouteMenus(array $menus, int $parentId = 0): array
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ((int)$menu->parent_id !== $parentId) {
                continue;
            }

            if ((int)$menu->type === 3 || (int)$menu->visible !== 1) {
                continue;
            }

            $children = $this->buildRouteMenus($menus, (int)$menu->id);
            if (!$this->canAccessMenu($menu) && $children === []) {
                continue;
            }

            $route = $this->serializeRouteMenu($menu, $menus);
            if ($children !== []) {
                $route['children'] = $children;
            }

            $tree[] = $route;
        }

        return $tree;
    }

    private function canAccessMenu(Menu $menu): bool
    {
        $permission = trim((string)$menu->permission);
        return $permission === '' || Yii::$app->user->can($permission);
    }

    /**
     * @param Menu[] $menus
     */
    private function serializeRouteMenu(Menu $menu, array $menus): array
    {
        $route = [
            'id' => (int)$menu->id,
            'path' => (string)$menu->path,
            'name' => (string)$menu->name,
            'component' => (string)$menu->component,
            'meta' => [
                'title' => $menu->title,
                'icon' => $menu->icon ?: null,
                'keepAlive' => (bool)$menu->keep_alive,
                'isHide' => (int)$menu->visible !== 1,
            ],
        ];

        $authList = $this->buildAuthList($menus, (int)$menu->id);
        if ($authList !== []) {
            $route['meta']['authList'] = $authList;
        }

        if ((int)$menu->is_external === 1 && $menu->external_url) {
            $route['meta']['link'] = $menu->external_url;
        }

        if ($menu->path === 'console' || $menu->path === '/dashboard/console') {
            $route['meta']['fixedTab'] = true;
        }

        return $route;
    }

    /**
     * @param Menu[] $menus
     */
    private function buildAuthList(array $menus, int $parentId): array
    {
        $authList = [];

        foreach ($menus as $menu) {
            if ((int)$menu->parent_id !== $parentId || (int)$menu->type !== 3) {
                continue;
            }

            $permission = trim((string)$menu->permission);
            if ($permission === '' || !Yii::$app->user->can($permission)) {
                continue;
            }

            $authList[] = [
                'title' => $menu->title,
                'authMark' => $permission,
            ];
        }

        return $authList;
    }

    private function appendUserCenterRoute(array $routes): array
    {
        $userCenterRoute = [
            'path' => 'user-center',
            'name' => 'UserCenter',
            'component' => '/system/user-center',
            'meta' => [
                'title' => 'menus.system.userCenter',
                'isHide' => true,
                'keepAlive' => true,
                'isHideTab' => true,
            ],
        ];

        foreach ($routes as &$route) {
            if (($route['name'] ?? '') !== 'System') {
                continue;
            }

            $route['children'] ??= [];
            foreach ($route['children'] as $child) {
                if (($child['name'] ?? '') === 'UserCenter') {
                    return $routes;
                }
            }

            $route['children'][] = $userCenterRoute;

            return $routes;
        }

        $routes[] = [
            'path' => '/system',
            'name' => 'System',
            'component' => '/index/index',
            'meta' => [
                'title' => 'menus.system.title',
                'icon' => 'ri:user-3-line',
                'isHide' => true,
            ],
            'children' => [$userCenterRoute],
        ];

        return $routes;
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'         => (int)$user->id,
            'username'   => $user->username,
            'real_name'  => $user->real_name ?: '',
            'mobile'     => $user->mobile ?: '',
            'email'      => $user->email,
            'avatar'     => $user->avatar ?: '',
            'status'     => (int)$user->status,
            'statusText' => $this->getStatusText((int)$user->status),
            'roles'      => array_keys(Yii::$app->authManager->getRolesByUser((int)$user->id)),
            'created_at' => (int)$user->created_at,
            'updated_at' => (int)$user->updated_at,
        ];
    }

    private function getStatusText(int $status): string
    {
        return match ($status) {
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_INACTIVE => 'Inactive',
            User::STATUS_DELETED => 'Deleted',
            default => 'Unknown',
        };
    }
}
