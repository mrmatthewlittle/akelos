<?php

# This file is part of the Akelos Framework
# (Copyright) 2004-2010 Bermi Ferrer bermi a t bermilabs com
# See LICENSE and CREDITS for details

class AkStringToArray
{
    public function convert() {
        return Ak::toArray($this->source);
    }
}
