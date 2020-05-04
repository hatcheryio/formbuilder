<?php

use \Kirby\Database\Db;
use \Kirby\Cms\Response;
use \Kirby\Cms\App as Kirby;

Kirby::plugin('cre8ivclick/formbuilder', [
    'blueprints' => [
        'formbuilder' => __DIR__ . '/blueprints/formbuilder.yml',
        'formbuilder/fields/name' => __DIR__ . '/blueprints/fields/name.yml',
        'formbuilder/fields/class' => __DIR__ . '/blueprints/fields/class.yml',
        'formbuilder/fields/req' => __DIR__ . '/blueprints/fields/req.yml',
        'formbuilder/fields/label' => __DIR__ . '/blueprints/fields/label.yml',
        'formbuilder/fields/placeholder' => __DIR__ . '/blueprints/fields/placeholder.yml',
        'formbuilder/fields/value' => __DIR__ . '/blueprints/fields/value.yml',
        'formbuilder/fields/min' => __DIR__ . '/blueprints/fields/min.yml',
        'formbuilder/fields/max' => __DIR__ . '/blueprints/fields/max.yml',
        'formbuilder/fields/pattern' => __DIR__ . '/blueprints/fields/pattern.yml',
        'formbuilder/blocks/fb_text' => __DIR__ . '/blueprints/blocks/fb_text.yml',
        'formbuilder/blocks/fb_textarea' => __DIR__ . '/blueprints/blocks/fb_textarea.yml',
        'formbuilder/blocks/fb_email' => __DIR__ . '/blueprints/blocks/fb_email.yml',
        'formbuilder/blocks/fb_tel' => __DIR__ . '/blueprints/blocks/fb_tel.yml',
        'formbuilder/blocks/fb_number' => __DIR__ . '/blueprints/blocks/fb_number.yml',
        'formbuilder/blocks/fb_url' => __DIR__ . '/blueprints/blocks/fb_url.yml',
        'formbuilder/blocks/fb_checkbox' => __DIR__ . '/blueprints/blocks/fb_checkbox.yml',
        'formbuilder/blocks/fb_radio' => __DIR__ . '/blueprints/blocks/fb_radio.yml',
        'formbuilder/blocks/fb_select' => __DIR__ . '/blueprints/blocks/fb_select.yml',
        'formbuilder/blocks/fb_date' => __DIR__ . '/blueprints/blocks/fb_date.yml',
        'formbuilder/blocks/fb_time' => __DIR__ . '/blueprints/blocks/fb_time.yml',
        'formbuilder/blocks/fb_password' => __DIR__ . '/blueprints/blocks/fb_password.yml',
        'formbuilder/blocks/fb_hidden' => __DIR__ . '/blueprints/blocks/fb_hidden.yml',
        'formbuilder/blocks/fb_honeypot' => __DIR__ . '/blueprints/blocks/fb_honeypot.yml',
        'formbuilder/blocks/fb_line' => __DIR__ . '/blueprints/blocks/fb_line.yml',
        'formbuilder/blocks/fb_displaytext' => __DIR__ . '/blueprints/blocks/fb_displaytext.yml'
    ],
    'snippets' => [
        'formbuilder' => __DIR__ . '/snippets/formbuilder.php',
        'formbuilder/input' => __DIR__ . '/snippets/fields/input.php',
        'formbuilder/password' => __DIR__ . '/snippets/fields/password.php',
        'formbuilder/textarea' => __DIR__ . '/snippets/fields/textarea.php',
        'formbuilder/number' => __DIR__ . '/snippets/fields/number.php',
        'formbuilder/checkbox' => __DIR__ . '/snippets/fields/checkbox.php',
        'formbuilder/select' => __DIR__ . '/snippets/fields/select.php',
        'formbuilder/radio' => __DIR__ . '/snippets/fields/radio.php',
        'formbuilder/hidden' => __DIR__ . '/snippets/fields/hidden.php',
        'formbuilder/honeypot' => __DIR__ . '/snippets/fields/honeypot.php',
        'formbuilder/line' => __DIR__ . '/snippets/fields/line.php',
        'formbuilder/displaytext' => __DIR__ . '/snippets/fields/displaytext.php'
    ],
    'templates' => [
        'emails/fb_default' => __DIR__ . '/templates/emails/fb_default.php'
    ],
    'routes' => [
        [
            'pattern' => 'formbuilder/formhandler',
            'method' => 'POST',
            'action'  => function () {
                // VALIDATION CHECKES:
                $data = get();
                try {
                    // start by checking whether this is a formbuilder submission -
                    // by checking, for example, whether we can get a page id:
                    if (!isset($data['fb_pg_id'])) {
                        throw new Exception('Page ID not found.', 400);
                    }
                    // then, check whether the page exists:
                    if (!$pg = page($data['fb_pg_id'])) {
                        throw new Exception('Page ID does not exist.', 406);
                    } else {
                        // page ID has now been stored in the $pg variable,
                        // so we can remove the pg_id field from our data array:
                        unset($data['fb_pg_id']);
                    }

                    foreach ($pg->builder()->toBuilderBlocks() as $block) :
                        // Check if the fb_builder property exists, which means that this is our form block
                        if ($block->fb_builder()->isNotEmpty()) {
                            /**
                             * Formbuilder only accepts a page as an input property, not the data itself.
                             * We need to manually update the content property of the page with our block data to get formbuilder to accept the page object.
                             */
                            $pg->content()->update($block->toArray());
                        }
                    endforeach;

                    // then, check whether the page has all the fields required for our logic:
                    if (!$pg->fb_builder()->exists() or !$pg->fb_is_ajax()->exists() or !$pg->fb_captcha()->exists() or !$pg->fb_send_email()->exists()) {
                        throw new Exception('Page does not have needed fields.', 422);
                    }
                    // if user has selected to redirect to success/error pages,
                    // let's make sure these pages exist:
                    $ajax = $pg->fb_is_ajax()->toBool();
                    $sPage = $pg->fb_success_page()->exists() ? $pg->fb_success_page()->toPage() : false;
                    $ePage = $pg->fb_error_page()->exists() ? $pg->fb_error_page()->toPage() : false;
                    if (!$sPage or !$ePage) {
                        throw new Exception('Missing success/error page.', 422);
                    }

                    // check the CSRF token:
                    if (!isset($data['fb_csrf']) or csrf($data['fb_csrf']) !== true) {
                        throw new Exception('No valid CSRF token.', 403);
                    } else {
                        // passed CSRF check, so we can delete the CSRF field from our data array:
                        unset($data['fb_csrf']);
                    }

                    // check honeypots:
                    $honeyfields = $pg->fb_builder()->toBuilderBlocks()->filterBy('_key', '==', 'fb_honeypot');
                    if (count($honeyfields) > 0) {
                        foreach ($honeyfields as $field) {
                            // the honeypot field should be empty -
                            // if it's not, it was probably filled in by a spammer bot:
                            if (!empty($data[$field->field_name()->value()])) {
                                throw new Exception('Bot submission detected.', 403);
                            }
                            // honeypot is empty - we can remove the field from our data array:
                            unset($data[$field->field_name()->value()]);
                        }
                    }
                    // hCaptcha check - if it is being used:
                    if ($pg->fb_captcha()->toBool() and $pg->fb_captcha_sitekey()->isNotEmpty() and $pg->fb_captcha_secretkey()->isNotEmpty()) {
                        // check that the hCaptcha token has been set -
                        // that is, the captcha has been answered and submitted::
                        if (isset($data['h-captcha-response']) and !empty($data['h-captcha-response'])) {
                            $hData = [
                                'response' => $data['h-captcha-response'],
                                'secret' => $pg->fb_captcha_secretkey()->value()
                            ];
                            $options = [
                                'method'  => 'POST',
                                'data'    => http_build_query($hData)
                            ];
                            $response = Remote::request('https://hcaptcha.com/siteverify', $options);
                            $response = $response->json();
                            // check whether the hCaptcha was answered successfully::
                            if ($response['success'] == false) {
                                throw new Exception('hCaptcha not validated.', 403);
                            } else {
                                // captcha has been answered successfully,
                                // so we can now remove it from our data array:
                                unset($data['h-captcha-response']);
                                if (isset($data['g-recaptcha-response'])) {
                                    unset($data['g-recaptcha-response']);
                                }
                            }
                        } else {
                            // captcha token hasn't been set - possible form hijacking:
                            throw new Exception('hCaptcha expected.', 403);
                        }
                    }

                    // remove unnecessary fields from the data array,
                    // so they don't get included in the sent/stored data:
                    unset($data['submit']); // remove the value sent by the 'submit' button
                    foreach ($data as $field => $value) {
                        // remove any fields with empty values:
                        if (empty($data[$field])) {
                            unset($data[$field]);
                        }
                    }



                    // TODO data validation: required, type, pattern matching?



                    $double_optin_token = bin2hex(random_bytes(16));
                    $project_id = site()->config_project_id()->toString();
                    if (empty($project_id)) {
                        throw new Exception('Invalid project id - please set one in the panel: site > settings > general > project id.', 422);
                    }

                    // STORING THE SUBMISSION IN THE DATABASE:
                    // gather the values we need to store:
                    if ($pg->fb_key_field()->exists() and $pg->fb_key_field()->isNotEmpty() and isset($data[$pg->fb_key_field()->value()])) {
                        // This is the unique identifier, we can only allow one combination of form-id + identifier in the database
                        $unique_identifier = $data[$pg->fb_key_field()->value()];
                    } else {
                        // Use timestamp as unique value, if we allow duplicate entries
                        $unique_identifier = time();
                    }
                    // build the content of the submission in readable format:
                    try {
                        $parsed_form_content = json_encode($data);
                    } catch (Exception $error) {
                        throw new Exception('Invalid form content', 422);
                    }

                    // Save information to the database
                    // Verify that the table exists
                    $dbInstance = Db::connect();
                    $tableExists = $dbInstance->validateTable('form_submissions');
                    if (!$tableExists) {
                        $dbInstance->query('CREATE TABLE `form_submissions` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `token` varchar(32) NOT NULL,
                            `email_confirmed` tinyint(1) DEFAULT NULL,
                            `project_id` varchar(32) NOT NULL,
                            `identifier` varchar(100) NOT NULL,
                            `form` varchar(100) NOT NULL,
                            `value` text NOT NULL,
                            `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `unique_identifier` (`project_id`,`form`,`identifier`) USING BTREE,
                            UNIQUE KEY `token` (`token`)
                        )');
                    }
                    $inserted = Db::insert('form_submissions', [
                        "token" => $double_optin_token,
                        "email_confirmed" => $pg->fb_email_double_optin_active()->toBool() ? 0 : NULL,
                        "form" => $pg->fb_form_id(),
                        "identifier" => $unique_identifier,
                        "project_id" => $project_id,
                        "value" => $parsed_form_content,
                    ]);
                    if ($inserted === false) {
                        $lastError = $dbInstance->lastError();
                        kirby()->logToGrayLog(\Psr\Log\LogLevel::ERROR, 'Database insertion failed: ' . $lastError->getMessage());
                        if ($lastError->getCode() === "23000") {
                            throw new Exception('Duplicate submission', 422);
                        }
                        return false;
                    }

                    // Verify that all information that we need for sending emails is present
                    if ($pg->fb_send_email()->toBool() or $pg->fb_email_double_optin_active()->toBool()) {
                        $config_email_type = site()->config_email_type()->toString();
                        $config_email_host = site()->config_email_host()->toString();
                        $config_email_port = site()->config_email_port()->toInt($default = 0);
                        $config_email_security = site()->config_email_security()->toString();
                        $config_email_auth = site()->config_email_auth()->toBool($default = false);
                        $config_email_username = site()->config_email_username()->toString();
                        $config_email_password = site()->config_email_password()->toString();

                        $email_transport = [
                            'type' => $config_email_type,
                            'host' => $config_email_host,
                            'port' => $config_email_port,
                            'security' => $config_email_security,
                            'auth' => $config_email_auth,
                            'username' => $config_email_username,
                            'password' => $config_email_password,
                        ];
                    }

                    // EMAILING THE RECEIVED DATA TO ADMIN EMAIL
                    // check whether we need to send the data via email:
                    if ($pg->fb_send_email()->toBool() and $pg->fb_email_recipient()->exists() and $pg->fb_email_recipient()->isNotEmpty()) {
                        // determine the subject:
                        $subject = $pg->fb_email_subject()->or('Formularanfrage von ' . site()->url());
                        // determining which email template to use:
                        if (file_exists(kirby()->roots()->templates() . '/emails/fb.html.php') and file_exists(kirby()->roots()->templates() . '/emails/fb.text.php')) {
                            // user has created html email templates, so we use those:
                            $template = 'fb';
                        } else if (file_exists(kirby()->roots()->templates() . '/emails/fb.php')) {
                            // user has created a plain-text email template, so we'll use that:
                            $template = 'fb';
                        } else {
                            // we'll use our default template:
                            $template = 'fb_default';
                        }
                        // sending the email:
                        try {
                            kirby()->email([
                                'from' => $config_email_username,
                                'replyTo' => $config_email_username,
                                'to' => $pg->fb_email_recipient()->value(),
                                'subject' => $subject,
                                'template' => $template,
                                'data' => ['page_id' => $pg->id(), 'fields' => $data],
                                'transport' => $email_transport,
                            ]);
                        } catch (Exception $error) {
                            kirby()->logToGrayLog(\Psr\Log\LogLevel::ERROR, 'Mail server error: ' . $error->getMessage());
                        }
                    }

                    // DOUBLE OPT-IN - CONFIRMATION EMAIL TO USER
                    // check whether we need to send the data via email:
                    if ($pg->fb_email_double_optin_active()->toBool() and $pg->fb_email_double_optin_recipient()->isNotEmpty()) {
                        // we get the recipient from a panel field:
                        if ($pg->fb_email_double_optin_recipient()->isNotEmpty()) {
                            $recipient = $data[$pg->fb_email_double_optin_recipient()->value()];
                        } else {
                            throw new Exception('Missing email sender value', 422);
                        }
                        // determine the subject:
                        if ($pg->fb_email_double_optin_subject()->exists()) {
                            $subject = $pg->fb_email_double_optin_subject()->or('Please confirm your email address');
                        } else {
                            $subject = 'Please confirm your email address';
                        }
                        // Replace placeholders in email template with actual values
                        $email_template = $pg->fb_email_double_optin_mail_template()->toString();
                        preg_match_all('/{{([a-z0-9\-])+}}/', $email_template, $matches);
                        if (!empty($matches) && !empty($matches[0])) {
                            foreach ($matches[0] as $value) {
                                $valueName = str_replace(str_split('{}'), '', $value);
                                $email_template = str_replace($value, array_key_exists($valueName, $data) ? $data[$valueName] : '', $email_template);
                            }
                        }
                        // Get the custom set logo
                        $logo = $pg->fb_email_double_optin_mail_template_logo();
                        try {
                            kirby()->email([
                                'from' => $config_email_username,
                                'replyTo' => $config_email_username,
                                'to' => $recipient,
                                'subject' => $subject,
                                'template' => 'double-opt-in',
                                'data' => [
                                    'token' => $double_optin_token,
                                    'url' => site()->url(),
                                    'content' => kirbytext($email_template),
                                    'logo' => $logo,
                                    'primary_color' => '#' . site()->color_primary()->or('F60F60'),
                                    'secondary_color' => '#' . site()->color_secondary()->or('000000'),
                                    'contrast_color' => '#' . site()->color_contrast()->or('FFFFFF'),
                                    'off_white_color' => '#' . site()->color_off_white()->or('F0F0F0'),
                                ],
                                'transport' => $email_transport,
                            ]);
                        } catch (Exception $error) {
                            throw new Exception("Mail server error: $error->getMessage().");
                        }
                    }
                    // everything went fine - we have a successfull submission!:
                    if (!$ajax) {
                        // if the form is not using ajax, we send the user
                        // to the success page, with appropriate data & info:
                        $data = [
                            'fields' => $data,
                            'error' => ''
                        ];
                        return go($sPage->url());
                    }
                    // if the user is using ajax, we return a success message:
                    return ['msg' => 'Form submitted successfully'];
                } catch (Exception $e) {
                    // Render error page or send in JSON format
                    if (!$ajax) {
                        // Not ajax, send to error page
                        return $ePage->render(['errorCode' => $e->getCode(), 'errorMessage' => $e->getMessage()]);
                    }
                    $response = new Response();
                    return $response->json(['Warning' => 'Server error: ' . $e->getMessage()], $e->getCode() ?? 500);
                }
            }
        ]
    ]
]);
