<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Estimate Artifact Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for uploaded estimate PDFs and the derived floorplan PNGs.
    | Kept separate from FILESYSTEM_DISK so a misconfigured default disk
    | (e.g. "public") can never accidentally expose customer files — these
    | paths are only served through auth- or token-gated controllers.
    |
    */

    'disk' => env('ESTIMATE_DISK', 'local'),

];
