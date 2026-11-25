<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

class UsersTable extends Table
{
    /**
     * Exclude disabled accounts from authentication.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findActive(SelectQuery $query): SelectQuery
    {
        return $query->where([
            $this->aliasField('active') => true,
            'OR' => [
                $this->aliasField('verification_required') => false,
                $this->aliasField('email_verified') . ' IS NOT' => null,
            ],
        ]);
    }
}
