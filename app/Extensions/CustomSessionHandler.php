<?php

namespace App\Extensions;

use App\Models\UserSession;
use DateInterval;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SessionHandlerInterface;

class CustomSessionHandler implements SessionHandlerInterface
{
    public function open($savePath, $sessionName)
    {
    }

    public function close()
    {
    }

    //The read method should return the string version of the session data associated with the given $sessionId.
    //There is no need to do any serialization or other encoding when retrieving or storing session data in your
    //driver, as Laravel will perform the serialization for you.
    public function read($sessionId)
    {
        ////Log::debug(__METHOD__ . ': A');
        return UserSession::where('token', $sessionId)->first();
    }

    //The write method should write the given $data string associated with the $sessionId to some persistent
    //storage system, such as MongoDB or another storage system of your choice. Again, you should not perform any
    //serialization - Laravel will have already handled that for you.
    public function write($sessionId, $data){
        ////Log::debug(__METHOD__ . ': A');
        //return;
        $UserSession = $this->read($sessionId) || new UserSession();
        $UserSession->user_id = (auth()->check()) ? auth()->user()->id : null;
        $UserSession->remote_addr = '';
        $UserSession->expiration_type = 0;
        $UserSession->expiration_date = new DateTime('now');
        $UserSession->payload = base64_encode($data);
        $UserSession->last_activity = time();
        ////Log::debug(__METHOD__ . ': B');

        $tokenDuration = env('SESSION_LIFETIME', (60 * 24));
        if (isset($params['tokenDuration']) && $params['tokenDuration'] > 0 && $params['tokenDuration'] <= $tokenDuration) {
            $tokenDuration = $params['tokenDuration'];
        }

        $UserSession->expiration_date->add(
            DateInterval::createFromDateString($tokenDuration . ' minutes')
        );
        ////Log::debug(__METHOD__ . ': C');

        $UserSession->save();
        ////Log::debug(__METHOD__ . ': D');

    }

    public function destroy($sessionId)
    {

        return;
    }

    public function gc($lifetime)
    {
        return;
    }

}
