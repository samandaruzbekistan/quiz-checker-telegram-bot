<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function createUser($data)
    {
        return User::create($data);
    }

    public function getUserByChatId($chat_id)
    {
        return User::where('chat_id', $chat_id)->first();
    }

    public function updateUser($chat_id, $data)
    {
        return User::where('chat_id', $chat_id)->update($data);
    }

    public function deleteUser($chat_id)
    {
        return User::where('chat_id', $chat_id)->delete();
    }

    public function getAllUsers()
    {
        return User::all();
    }
}