<?php

namespace App\Modules\User\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use App\Core\Oauth2\AccessToken;
use App\Core\Rbac\Permission;
use App\Core\Rbac\Role;
use App\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class UserDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function run()
    {
        Model::unguard();

        // Creating first user and assigning it to Admin role

        $user = User::where('email', 'user@friendly-solutions.com')->first();

        if ($user === null) {
            $user = User::create(
                [
                    'email'    => 'user@friendly-solutions.com',
                    'password' => \Hash::make('test'),
                ]
            );

            $this->command->info('User `user@friendly-solutions.com` has been created.');
            Role::ADMIN();

            // assign to user
            $admin = Role::ADMIN();
            $user->attachRole($admin);

            $this->command->info("Role {$admin->name} has been assigned to user {$user->email}");
        }
        // Creating test user
        /** @var User $user */
        $user = User::where('email', User::TEST_USER_EMAIL)->first();

        if ($user) {
            // resets password to default
            $user->setDirectLoginToken(User::TEST_USER_DIRECT_LOGIN_TOKEN);
            $user->setPassword('test');
            $user->save();
        } else {
            $user = User::create(
                [
                    'email'    => User::TEST_USER_EMAIL,
                    'password' => \Hash::make('test'),
                ]
            );

            $this->command->info('User `' . User::TEST_USER_EMAIL
                . '` has been created.');

            // assign to user
            $admin = Role::ADMIN();
            $user->attachRole($admin);

            $this->command->info("Role {$admin->name} has been assigned to user {$user->email}");
        }

        // Remove test tokens and create new one
        AccessToken::where('id', 'LIKE', 'TEST_%')->delete();
        $token = AccessToken::createForTesting();
        $token
            ->setExpireTime(strtotime('05-11-2514'))
            ->save();

        // Standard module permissions

        Model::unguard();

        $list = $this->getPermissions();

        $result = Permission::set('user', $list);

        foreach ($result as $info) {
            $this->command->info($info);
        }

        $this->call(__NAMESPACE__ . '\\UserDeviceDatabaseSeeder');
        $this->call(__NAMESPACE__ . '\\UserDeviceTokenDatabaseSeeder');
    }

    /**
     * Get permissions list
     */
    public function getPermissions()
    {
        return [
            'user.index'   => 'User index',
            'user.show'    => 'User show',
            'user.store'   => 'User store',
            'user.update'  => 'User update',
            'user.destroy' => 'User destroy',
        ];
    }

    /**
     * Get custom rules to verify whether this seeder was launched
     *
     * @return array
     */
    public function getCustomVerificationRules()
    {
        return [
            [
                'type'  => 'count',
                'table' => 'users',
                'where' => [
                    'email' => 'user@friendly-solutions.com',
                ],
            ],
        ];
    }
}
