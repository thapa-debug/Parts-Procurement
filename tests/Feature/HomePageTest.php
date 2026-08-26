<?php

it('boots the application and serves the home page', function () {
    $this->get('/')->assertOk();
});
