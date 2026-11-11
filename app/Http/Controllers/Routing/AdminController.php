<?php

namespace App\Http\Controllers\Routing;

use App\Core\Repositories\{
    TacticRepository,
    TeamRepository,
    UserRepository,
    DemoRepository,
};
use App\Http\Controllers\Controller;
use App\Models\{
    Team,
    User,
    UserInfo,
    UserRole,
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        private TacticRepository $tacticRepository,
        private TeamRepository $teamRepository,
        private UserRepository $userRepository,
        private DemoRepository $demoRepository,
    ) {}

    public function loginAs($id)
    {
        $user = User::find($id);
        UserInfo::where('uuid', $user->uuid)->firstOrCreate([
            'uuid' => $user->uuid,
        ]);

        $user = User::where('id', $user->id)->with(['profilePicture'])->with(['userInfo'])->with(['userRole'])->first();
        $user->userInfo->last_online = now();
        $user->userInfo->save();

        Auth::login($user);

        return redirect('/profile');
    }

    public function admin()
    {
        return redirect('/admin/dashboard/users');
    }
}
