<?php

namespace Cmgmyr\Messenger\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Cviebrock\EloquentSluggable\Sluggable;

class Thread extends Eloquent
{
    use SoftDeletes;
    use Sluggable;
    use SluggableScopeHelpers;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'threads';

    protected $fillable = ['subject','group_id','description','link_preview_id','owner_id'];

    /**
     * The attributes that can be set with Mass Assignment.
     *
     * @var array
     */
    //protected $fillable = [''];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $attributes = [])
    {
        $this->table = Models::table('threads');

        parent::__construct($attributes);
    }

    /**
     * Messages relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages()
    {
        return $this->hasMany(Models::classname(Message::class), 'thread_id', 'id');
    }

    /**
     * Messages relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messageNotification()
    {
        return $this->hasOne(Models::classname(Message::class), 'thread_id', 'id');
    }

    /**
     * Returns the latest message from a thread.
     *
     * @return \Cmgmyr\Messenger\Models\Message
     */
    public function getLatestMessageAttribute()
    {
        return $this->messages()->latest()->first();
    }

    /**
     * Participants relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function participants()
    {
        return $this->hasMany(Models::classname(Participant::class), 'thread_id', 'id');
    }

    /**
     * Returns the user object that created the thread.
     *
     * @return mixed
     */
    public function creator()
    {
        return $this->messages()->oldest()->first()->user;
    }

    public function linkPreview()
    {
        return $this->belongsTo('App\Models\LinkPreview');
    }

    public function group()
    {
        return $this->belongsTo('App\Models\Group');
    }

    public function userDetails()
    {
        return $this->belongsTo('App\Models\UserDetail','owner_id','user_id');
    }

    /**
     * Returns the user object that created the thread.
     *
     * @return mixed
     */
    public function creatorThread()
    {
        $creator = array();
        $creator['id'] = $this->userDetails['user_id'];
        $creator['name'] = $this->userDetails['first_name'].' '.$this->userDetails['last_name'];
        return $creator;
    }

    /**
     * Returns all of the latest threads by updated_at date.
     *
     * @return mixed
     */
    public static function getAllLatest()
    {
        return self::latest('updated_at');
    }

    /**
     * Returns all messages from a thread.
     *
     * @return \Cmgmyr\Messenger\Models\Message
     */
    public function getMessages()
    {
        return $this->messages()->latest()->get();
    }

    /**
     * Returns all threads by subject.
     *
     * @return mixed
     */
    public static function getBySubject($subjectQuery)
    {
        return self::where('subject', 'like', $subjectQuery)->get();
    }

    /**
     * Returns an array of user ids that are associated with the thread.
     *
     * @param null $userId
     *
     * @return array
     */
    public function participantsUserIds($userId = null)
    {
        $users = $this->participants()->withTrashed()->lists('user_id');

        if ($userId) {
            $users[] = $userId;
        }

        return $users;
    }

    /**
     * Returns threads that the user is associated with.
     *
     * @param $query
     * @param $userId
     *
     * @return mixed
     */
    public function scopeForUser($query, $userId)
    {
        $participantsTable = Models::table('participants');
        $threadsTable = Models::table('threads');

        return $query->join($participantsTable, $this->getQualifiedKeyName(), '=', $participantsTable . '.thread_id')
            ->where($participantsTable . '.user_id', $userId)
            ->where($participantsTable . '.deleted_at', null)
            ->where(function($query) use($threadsTable)
             {
                $query->where($threadsTable.'.subject','<>','TeamInvite')
                      ->where($threadsTable.'.subject','<>','Teamaccepted')
                      ->where($threadsTable.'.subject','<>','Connect')
                      ->where($threadsTable.'.subject','<>','Liked')
                      ->where($threadsTable.'.subject','<>','FeedCommented')
                      ->where($threadsTable.'.subject','<>','GroupCommented')
                      ->where($threadsTable.'.subject','<>','GroupInvited')
                      ->where($threadsTable.'.subject','<>','Shared');
             })                                           
            ->select($threadsTable . '.*');
    }

    /**
     * Returns threads as notifications for the logged in user.
     *
     * @param $query
     * @param $userId
     *
     * @return mixed
     */
    public function scopeForUserNotifications($query, $userId)
    {
        $participantsTable = Models::table('participants');
        $threadsTable = Models::table('threads');   

        //this query joins participants table with threads table and messages table with participants 
        //and returns query builder with threads.
        return $query->join($participantsTable, $this->getQualifiedKeyName(), '=', $participantsTable . '.thread_id')
                    ->join('messages',function($join)use($participantsTable){
                        $join->on('messages.thread_id','=',$participantsTable . '.thread_id');
                    })
            ->where(function($query) use($threadsTable)
            {
                $query->orWhere($threadsTable.'.subject','TeamInvite')
                      ->orWhere($threadsTable.'.subject','Teamaccepted')
                      ->orWhere($threadsTable.'.subject','Connect')
                      ->orWhere($threadsTable.'.subject','Liked')
                      ->orWhere($threadsTable.'.subject','Shared')
                      ->orWhere($threadsTable.'.subject','FeedCommented')
                      ->orWhere($threadsTable.'.subject','GroupCommented')
                      ->orWhere($threadsTable.'.subject','GroupInvited');
            })
            ->where($participantsTable . '.user_id', $userId)
            ->where($participantsTable . '.deleted_at', null)
            ->where('messages.user_id','<>',$userId)
            ->select($threadsTable . '.*');
    }

    /**
     * Returns threads with new messages that the user is associated with.
     *
     * @param $query
     * @param $userId
     *
     * @return mixed
     */
    public function scopeForUserWithNewMessages($query, $userId)
    {
        $participantTable = Models::table('participants');
        $threadsTable = Models::table('threads');

        return $query->join($participantTable, $this->getQualifiedKeyName(), '=', $participantTable . '.thread_id')
            ->where(function($query) use($threadsTable)
             {
                $query->where($threadsTable.'.subject','<>','TeamInvite')
                      ->where($threadsTable.'.subject','<>','Teamaccepted')
                      ->where($threadsTable.'.subject','<>','Connect')
                      ->where($threadsTable.'.subject','<>','Liked')
                      ->where($threadsTable.'.subject','<>','FeedCommented')
                      ->where($threadsTable.'.subject','<>','GroupCommented')
                      ->where($threadsTable.'.subject','<>','GroupInvited')
                      ->where($threadsTable.'.subject','<>','Shared');
             })   
            ->where($participantTable . '.user_id', $userId)
            ->whereNull($participantTable . '.deleted_at')
            ->where(function ($query) use ($participantTable, $threadsTable) {
                $query->where($threadsTable . '.updated_at', '>', $this->getConnection()->raw($this->getConnection()->getTablePrefix() . $participantTable . '.last_read'))
                    ->orWhereNull($participantTable . '.last_read');
            })
            ->select($threadsTable . '.*');
    }

    /**
     * Returns threads between given user ids.
     *
     * @param $query
     * @param $participants
     *
     * @return mixed
     */
    public function scopeBetween($query, array $participants)
    {
        $query->where(function($query)
             {
                $query->where('threads.subject','<>','TeamInvite')
                      ->where('threads.subject','<>','Teamaccepted')
                      ->where('threads.subject','<>','Connect')
                      ->where('threads.subject','<>','Liked')
                      ->where('threads.subject','<>','FeedCommented')
                      ->where('threads.subject','<>','GroupCommented')
                      ->where('threads.subject','<>','GroupInvited')
                      ->where('threads.subject','<>','Shared');
             })   
        ->whereHas('participants', function ($query) use ($participants) {
            $query->whereIn('user_id', $participants)
                ->groupBy('thread_id')
                ->havingRaw('COUNT(thread_id)=' . count($participants));
        });
    }

    /**
     * Adds users to this thread.
     *
     * @param array $participants list of all participants
     */
    public function addParticipants(array $participants)
    {
        if (count($participants)) {
            foreach ($participants as $user_id) {
                Models::participant()->firstOrCreate([
                    'user_id' => $user_id,
                    'thread_id' => $this->id,
                ]);
            }
        }
    }

    /**
     * Mark a thread as read for a user.
     *
     * @param int $userId
     */
    public function markAsRead($userId)
    {
        try {
            $participant = $this->getParticipantFromUser($userId);
            $participant->last_read = new Carbon();
            $participant->save();
        } catch (ModelNotFoundException $e) {
            // do nothing
        }
    }

    /**
     * See if the current thread is unread by the user.
     *
     * @param int $userId
     *
     * @return bool
     */
    public function isUnread($userId)
    {
        try {
            $participant = $this->getParticipantFromUser($userId);
            if ($this->updated_at > $participant->last_read) {
                return true;
            }
        } catch (ModelNotFoundException $e) {
            // do nothing
        }

        return false;
    }

    /**
     * Finds the participant record from a user id.
     *
     * @param $userId
     *
     * @return mixed
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getParticipantFromUser($userId)
    {
        return $this->participants()->where('user_id', $userId)->firstOrFail();
    }

    /**
     * Restores all participants within a thread that has a new message.
     */
    public function activateAllParticipants()
    {
        $participants = $this->participants()->withTrashed()->get();
        foreach ($participants as $participant) {
            $participant->restore();
        }
    }

    /**
     * Generates a string of participant information.
     *
     * @param null  $userId
     * @param array $columns
     *
     * @return string
     */
    public function participantsString($userId = null, $columns = ['name'])
    {
        $participantsTable = Models::table('participants');
        $usersTable = Models::table('users');

        $selectString = $this->createSelectString($columns);

        $participantNames = $this->getConnection()->table($usersTable)
            ->join($participantsTable, $usersTable . '.id', '=', $participantsTable . '.user_id')
            ->where($participantsTable . '.thread_id', $this->id)
            ->select($this->getConnection()->raw($selectString));

        if ($userId !== null) {
            $participantNames->where($usersTable . '.id', '!=', $userId);
        }

        $userNames = $participantNames->lists($usersTable . '.name');

        return implode(', ', $userNames);
    }

    /**
     * Checks to see if a user is a current participant of the thread.
     *
     * @param $userId
     *
     * @return bool
     */
    public function hasParticipant($userId)
    {
        $participants = $this->participants()->where('user_id', '=', $userId);
        if ($participants->count() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Generates a select string used in participantsString().
     *
     * @param $columns
     *
     * @return string
     */
    protected function createSelectString($columns)
    {
        $dbDriver = $this->getConnection()->getDriverName();
        $tablePrefix = $this->getConnection()->getTablePrefix();
        $usersTable = Models::table('users');

        switch ($dbDriver) {
        case 'pgsql':
        case 'sqlite':
            $columnString = implode(" || ' ' || " . $tablePrefix . $usersTable . '.', $columns);
            $selectString = '(' . $tablePrefix . $usersTable . '.' . $columnString . ') as name';
            break;
        case 'sqlsrv':
            $columnString = implode(" + ' ' + " . $tablePrefix . $usersTable . '.', $columns);
            $selectString = '(' . $tablePrefix . $usersTable . '.' . $columnString . ') as name';
            break;
        default:
            $columnString = implode(", ' ', " . $tablePrefix . $usersTable . '.', $columns);
            $selectString = 'concat(' . $tablePrefix . $usersTable . '.' . $columnString . ') as name';
        }

        return $selectString;
    }
    /**
     * Returns array of unread messages in thread for given user.
     *
     * @param $userId
     *
     * @return \Illuminate\Support\Collection
     */
    public function userUnreadMessages($userId)
    {
        $messages = $this->messages()->get();
        $participant = $this->getParticipantFromUser($userId);
        if (!$participant) {
            return collect();
        }
        if (!$participant->last_read) {
            return collect($messages);
        }
        $unread = [];
        $i = count($messages) - 1;
        while ($i) {
            if ($messages[$i]->updated_at->gt($participant->last_read)) {
                array_push($unread, $messages[$i]);
            } else {
                break;
            }
            --$i;
        }

        return collect($unread);
    }

    /**
     * Returns count of unread messages in thread for given user.
     *
     * @param $userId
     *
     * @return int
     */
    public function userUnreadMessagesCount($userId)
    {
        return $this->userUnreadMessages($userId)->count();
    }

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'subject'
            ]
        ];
    }

    /**
     * This method brings the unread notifications of logged in user.
     *
     * @return array
     */
    public static function unreadCount($threadIds)
    {
        $threadsWithNewMessages = [];

        //get all participants for the logged in user.
        $participants = Models::participant()->where('user_id', \Auth::id())->lists('last_read', 'thread_id');

        /**
         * @todo: see if we can fix this more in the future.
         * Illuminate\Foundation is not available through composer, only in laravel/framework which
         * I don't want to include as a dependency for this package...it's overkill. So let's
         * exclude this check in the testing environment.
         */
        if (getenv('APP_ENV') == 'testing' || !str_contains(\Illuminate\Foundation\Application::VERSION, '5.0')) {
            $participants = $participants->all();
        }

        // if there are participants, then get all threads for those participants.
        if ($participants) {
            $threads = Models::thread()->whereIn('id', $threadIds)->get();

            foreach ($threads as $thread) {

                //if last_read is not set then add it to unread array.
                if (!$participants[$thread->id]) {
                    $threadsWithNewMessages[] = $thread->id;
                }
            }
        }

        return count($threadsWithNewMessages);
    }

}
