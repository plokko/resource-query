<?php

namespace plokko\ResourceQuery\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
		/// Fix object to array conversion
        if(is_object($this->resource) && !($this->resource instanceof Arrayable) )
            return (array) $this->resource;
        return parent::toArray($request);
    }
}
