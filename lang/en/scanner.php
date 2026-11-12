<?php

// Texts for the camera scanner component (resources/views/scanner/_camera.blade.php).
return [
    'title' => 'Scan QR / barcode',
    'hint' => 'Point the camera at the QR or barcode (about a hand\'s width away) and wait for focus; use the slider to zoom in if it is small.',
    'loading' => 'Starting the camera and scanner...',
    'zoom' => 'Zoom',
    'zoom_in' => 'Zoom in',
    'zoom_out' => 'Zoom out',
    'torch' => 'Torch',
    'from_photo' => 'Take / choose a photo',
    'photo_reading' => 'Reading the photo...',
    'photo_not_found' => 'No QR or barcode found in the photo. Move closer so the code fills the frame and try again.',
    'insecure' => 'Browsers only allow camera access on HTTPS or localhost. Use an HTTPS address, or type the value in the field.',
    'camera_error' => 'Could not open the camera. Make sure the browser is allowed to use it, or type the value in the field.',
    'library_error' => 'Failed to load the scanner component (possibly a network issue). Type the value in the field.',
    'button' => 'Scan with camera',
];
