<?php

namespace Cmgmyr\Messenger\Traits;

use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Models;
use Cmgmyr\Messenger\Models\Participant;
use Cmgmyr\Messenger\Models\Thread;

trait Messagable
{
    /**
     * Message relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages()
    {
        return $this->hasMany(Models::classname(Message::class));
    }

    /**
     * Participants relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function participants()
    {
        return $this->hasMany(Models::classname(Participant::class));
    }

    /**
     * Thread relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function threads()
    {
        return $this->belongsToMany(
            Models::classname(Thread::class),
            Models::table('participants'),
            'user_id',
            'thread_id'
        );
    }

    /**
     * Returns the new messages count for user.
     *
     * @return int
     */
    public function newThreadsCount()
    {
        return count($this->threadsWithNewMessages());
    }

    /**
     * Returns the new messages count for user.
     *
     * @return int
     */
    public function newMessagesCount()
    {
        return count($this->threadsWithNewMessages());
    }

    /**
     * Returns all threads with new messages.
     *
     * @return array
     */
    public function threadsWithNewMessages()
    {
        $threadsWithNewMessages = [];

        $participants = Models::participant()->where('user_id', $this->id)->lists('last_read', 'thread_id');

        /**
         * @todo: see if we can fix this more in the future.
         * Illuminate\Foundation is not available through composer, only in laravel/framework which
         * I don't want to include as a dependency for this package...it's overkill. So let's
         * exclude this check in the testing environment.
         */
        if (getenv('APP_ENV') == 'testing' || !str_contains(\Illuminate\Foundation\Application::VERSION, '5.0')) {
            $participants = $participants->all();
        }
        if ($participants) {
            $threads = Models::thread()->whereIn('id', array_keys($participants))
                                       ->where(function($query)
                                        {
                                            $query->where('subject','<>','TeamInvite')
                                                  ->where('subject','<>','Teamaccepted')
                                                  ->where('subject','<>','Connect')
                                                  ->where('subject','<>','Liked')
                                                  ->where('subject','<>','Shared');
                                        })                                     
                                      ->get();

            foreach ($threads as $thread) {

                if (!$participants[$thread->id]) {
                    $threadsWithNewMessages[] = $thread->id;
                }
            }
        }

        return $threadsWithNewMessages;
    }

    /**
     * Returns all threads with new messages.
     *
     * @return array
     */
    public function threadsWithNewMessagesAndUsers()
    {
        $threadsWithNewMessages = [];

        $participants = Models::participant()->where('user_id', $this->id)->lists('last_read', 'thread_id');

        /**
         * @todo: see if we can fix this more in the future.
         * Illuminate\Foundation is not available through composer, only in laravel/framework which
         * I don't want to include as a dependency for this package...it's overkill. So let's
         * exclude this check in the testing environment.
         */
        if (getenv('APP_ENV') == 'testing' || !str_contains(\Illuminate\Foundation\Application::VERSION, '5.0')) {
            $participants = $participants->all();
        }
        if ($participants) {
            $threads = Models::thread()->whereIn('id', array_keys($participants))
                                       ->where(function($query)
                                        {
                                            $query->where('subject','<>','TeamInvite')
                                                  ->where('subject','<>','Teamaccepted')
                                                  ->where('subject','<>','Connect')
                                                  ->where('subject','<>','Liked')
                                                  ->where('subject','<>','Shared');
                                        })                               
                                       ->get();

            foreach ($threads as $key => $thread) {

                if (!$participants[$thread->id]) {
                    $threadsWithNewMessages[$key]['thread_id'] = $thread->id;
                    if (count($thread->messages)>0) {
                        $threadsWithNewMessages[$key]['user_id'] = $thread->messages[0]->user_id;
                        $threadsWithNewMessages[$key]['body'] = $thread->messages[0]->body;
                    }else{
                        $threadsWithNewMessages[$key]['user_id'] = Null;
                    }
                }
            }
        }

        return $threadsWithNewMessages;
    }

    /**
     * Returns all threads with new messages.
     *
     * @return array
     */
    public function threadsWithMessagesAndUsers()
    {
        $threadsWithNewMessages = [];

        $participants = Models::participant()->where('user_id', $this->id)->lists('last_read', 'thread_id');

        /**
         * @todo: see if we can fix this more in the future.
         * Illuminate\Foundation is not available through composer, only in laravel/framework which
         * I don't want to include as a dependency for this package...it's overkill. So let's
         * exclude this check in the testing environment.
         */
        if (getenv('APP_ENV') == 'testing' || !str_contains(\Illuminate\Foundation\Application::VERSION, '5.0')) {
            $participants = $participants->all();
        }
        //dd($participants);
        if ($participants) {
            $threads = Models::thread()->whereIn('id', array_keys($participants))
                                       ->where(function($query)
                                        {
                                            $query->where('subject','<>','TeamInvite')
                                                  ->where('subject','<>','Teamaccepted')
                                                  ->where('subject','<>','Connect')
                                                  ->where('subject','<>','Liked')
                                                  ->where('subject','<>','Shared');
                                        })                               
                                       ->get();

            foreach ($threads as $key => $thread) {

                    $threadsWithNewMessages[$key]['thread_id'] = $thread->id;
                    
                    if (count($thread->messages)>0) {
                        $threadsWithNewMessages[$key]['user_id'] = $thread->messages[0]->user_id;
                        $threadsWithNewMessages[$key]['body'] = $thread->messages[0]->body;
                    }else{
                        $threadsWithNewMessages[$key]['user_id'] = Null;
                    }
            }
        }

        return $threadsWithNewMessages;
    }
}
