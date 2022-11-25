<?php

namespace App\Modules\Person\Repositories;

use Illuminate\Support\Facades\App;
use App\Core\AbstractRepository;
use App\Modules\Person\Models\PersonNote;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PersonNote repository class
 */
class PersonNoteRepository extends AbstractRepository
{
    /**
     * Repository constructor
     *
     * @param Container  $app
     * @param PersonNote $personNote
     */
    public function __construct(Container $app, PersonNote $personNote)
    {
        parent::__construct($app, $personNote);
    }

    /**
     * Get notes
     *
     * @param $personId
     *
     * @return mixed
     */
    public function getNotes($personId)
    {
        return $this->model
            ->select([
                '*',
                DB::raw('person_name(person_id) as person_name'),
                DB::raw('person_name(created_by) as created_by_name')
            ])
            ->where('person_id', $personId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Delete note
     * @param $personId
     * @param $noteId
     */
    public function delete($personId, $noteId)
    {
        return $this->model
            ->where('person_note_id', $noteId)
            ->where('person_id', $personId)
            ->delete();
    }

    /**
     * @param  int  $id
     * @param  false  $full
     *
     * @return array
     */
    public function show($id, $full = false)
    {
        $result = $this->model
            ->select([
                '*',
                DB::raw('person_name(person_id) as person_name'),
                DB::raw('person_name(created_by) as created_by_name')
            ])
            ->where('person_note_id', $id)
            ->first();
        
        return ['item' => $result];
    }
    
    /**
     * @param $personId
     *
     * @return bool
     */
    public function isNoteAlert($personId)
    {
        return (bool)$this->model
            ->where('person_id', $personId)
            ->count();
    }
}
