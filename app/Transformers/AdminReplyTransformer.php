<?php

namespace App\Transformers;

use App\Models\AdminReply;
use League\Fractal\TransformerAbstract;

class ReplyTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['user', 'reply'];

    public function transform(AdminReply $adminreply)
    {
        return [
            'id' => $adminreply->id,
            'comment_id' => (int) $adminreply->comment_id,
            'from_user_id' => (int) $adminreply->from_user_id,
            'to_user_id' =>(int) $adminreply->to_user_id,
            'content' => $adminreply->content,
            'created_at' => (string) $adminreply->created_at,
            'updated_at' => (string) $adminreply->updated_at,
        ];
    }

    public function includeUser(AdminReply $adminreply)
    {
        return $this->item($adminreply->user, new UserTransformer());
    }

    public function includereply(AdminReply $adminreply)
	{
		return $this->item($adminreply->reply, new ReplyTransformer());
	}
}
