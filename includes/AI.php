<?php
/**
 * AI schema, validation, generation, and preset storage.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

use WP_Error;

final class AI
{
    private const OPENAI_RESPONSES_URL = 'https://api.openai.com/v1/responses';
    private const DEFAULT_MODEL = 'gpt-5.5';
    private const MAX_REFERENCE_IMAGES = 6;
    private const MAX_REFERENCE_IMAGE_BYTES = 8388608;
    private const MAX_SECTIONS = 12;
    private const MAX_COLUMNS = 3;
    private const MAX_ELEMENTS_PER_COLUMN = 12;
    private const MAX_ICON_LIST_ITEMS = 10;
    private const MAX_FAQ_ITEMS = 8;
    private const ALLOWED_REFERENCE_IMAGE_MIME_TYPES = array(
        'image/jpeg',
        'image/png',
        'image/webp',
    );
    private const ALLOWED_LAYOUTS = array(
        'one',
        'two_equal',
        'two_left',
        'two_right',
        'three_equal',
    );
    private const ALLOWED_ELEMENT_TYPES = array(
        'heading',
        'text',
        'button',
        'image',
        'icon_list',
        'spacer',
        'divider',
        'faq',
    );

    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function get_schema(): array
    {
        return array(
            'schema_version' => 2,
            'title'          => 'ZHD Elementor Compiler Schema',
            'description'    => 'Structured layout payload for AI-assisted design generation. Presets compile into native Elementor sections, columns, and core widgets.',
            'output_shape'   => array(
                'title'       => 'string',
                'description' => 'string',
                'source'      => 'manual|openai|import',
                'sections'    => 'array<section>',
            ),
            'section_shape'  => array(
                'layout'  => 'one|two_equal|two_left|two_right|three_equal',
                'columns' => 'array<column>',
            ),
            'column_shape'   => array(
                'elements' => 'array<element>',
            ),
            'element_types'  => array(
                'heading' => array(
                    'text'  => 'string',
                    'level' => 'h1|h2|h3|h4',
                    'size'  => 'xl|large|medium|small',
                ),
                'text' => array(
                    'text' => 'string',
                ),
                'button' => array(
                    'text'  => 'string',
                    'url'   => 'url',
                    'style' => 'primary|secondary|link',
                    'align' => 'left|center|right',
                ),
                'image' => array(
                    'image_url' => 'url',
                    'alt'       => 'string',
                    'aspect'    => 'portrait|square|landscape',
                ),
                'icon_list' => array(
                    'items' => 'array<{text:string, icon:string}>',
                ),
                'spacer' => array(
                    'size' => 'small|medium|large',
                ),
                'divider' => array(),
                'faq' => array(
                    'items' => 'array<{question:string, answer:string}>',
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function validate_layout_payload(array $payload)
    {
        $title = sanitize_text_field((string) ($payload['title'] ?? ''));
        $description = sanitize_textarea_field((string) ($payload['description'] ?? ''));
        $source = sanitize_key((string) ($payload['source'] ?? 'manual'));

        if ('' === $title) {
            return new WP_Error('zhd_ai_missing_title', __('AI preset validation failed: title is required.', ZHD_EB_TEXT_DOMAIN));
        }

        if (! in_array($source, array('manual', 'openai', 'import'), true)) {
            $source = 'manual';
        }

        $sections = $payload['sections'] ?? array();

        if (is_array($sections) && array() !== $sections) {
            $sanitized_sections = $this->sanitize_sections($sections);

            if (array() === $sanitized_sections) {
                return new WP_Error('zhd_ai_invalid_sections', __('AI preset validation failed: no valid sections were found.', ZHD_EB_TEXT_DOMAIN));
            }

            return array(
                'title'       => $title,
                'description' => $description,
                'source'      => $source,
                'sections'    => $sanitized_sections,
            );
        }

        return new WP_Error('zhd_ai_missing_sections', __('AI preset validation failed: at least one section is required.', ZHD_EB_TEXT_DOMAIN));
    }

    /**
     * @param array<int, mixed> $sections
     * @return array<int, array<string, mixed>>
     */
    private function sanitize_sections(array $sections): array
    {
        $sanitized_sections = array();

        foreach (array_slice($sections, 0, self::MAX_SECTIONS) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $layout = sanitize_key((string) ($section['layout'] ?? 'one'));
            if (! in_array($layout, self::ALLOWED_LAYOUTS, true)) {
                $layout = 'one';
            }

            $columns = array();
            foreach (array_slice((array) ($section['columns'] ?? array()), 0, self::MAX_COLUMNS) as $column) {
                if (! is_array($column)) {
                    continue;
                }

                $elements = $this->sanitize_elements((array) ($column['elements'] ?? array()));

                if (array() === $elements) {
                    continue;
                }

                $columns[] = array(
                    'elements' => $elements,
                );
            }

            if (array() === $columns) {
                continue;
            }

            $sanitized_sections[] = array(
                'layout'  => $layout,
                'columns' => $columns,
            );
        }

        return $sanitized_sections;
    }

    /**
     * @param array<int, mixed> $elements
     * @return array<int, array<string, mixed>>
     */
    private function sanitize_elements(array $elements): array
    {
        $sanitized_elements = array();

        foreach (array_slice($elements, 0, self::MAX_ELEMENTS_PER_COLUMN) as $element) {
            if (! is_array($element)) {
                continue;
            }

            $type = sanitize_key((string) ($element['type'] ?? ''));

            if (! in_array($type, self::ALLOWED_ELEMENT_TYPES, true)) {
                continue;
            }

            $sanitized = match ($type) {
                'heading' => $this->sanitize_heading_element($element),
                'text' => $this->sanitize_text_element($element),
                'button' => $this->sanitize_button_element($element),
                'image' => $this->sanitize_image_element($element),
                'icon_list' => $this->sanitize_icon_list_element($element),
                'spacer' => $this->sanitize_spacer_element($element),
                'divider' => array('type' => 'divider'),
                'faq' => $this->sanitize_faq_element($element),
                default => null,
            };

            if (null !== $sanitized) {
                $sanitized_elements[] = $sanitized;
            }
        }

        return $sanitized_elements;
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>|null
     */
    private function sanitize_heading_element(array $element): ?array
    {
        $text = sanitize_textarea_field((string) ($element['text'] ?? ''));

        if ('' === $text) {
            return null;
        }

        $level = sanitize_key((string) ($element['level'] ?? 'h2'));
        $size = sanitize_key((string) ($element['size'] ?? 'large'));

        return array(
            'type'  => 'heading',
            'text'  => $text,
            'level' => in_array($level, array('h1', 'h2', 'h3', 'h4'), true) ? $level : 'h2',
            'size'  => in_array($size, array('xl', 'large', 'medium', 'small'), true) ? $size : 'large',
        );
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>|null
     */
    private function sanitize_text_element(array $element): ?array
    {
        $text = sanitize_textarea_field((string) ($element['text'] ?? ''));

        return '' === $text ? null : array(
            'type' => 'text',
            'text' => $text,
        );
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>|null
     */
    private function sanitize_button_element(array $element): ?array
    {
        $text = sanitize_text_field((string) ($element['text'] ?? ''));

        if ('' === $text) {
            return null;
        }

        $style = sanitize_key((string) ($element['style'] ?? 'primary'));
        $align = sanitize_key((string) ($element['align'] ?? 'left'));

        return array(
            'type'  => 'button',
            'text'  => $text,
            'url'   => esc_url_raw((string) ($element['url'] ?? '')),
            'style' => in_array($style, array('primary', 'secondary', 'link'), true) ? $style : 'primary',
            'align' => in_array($align, array('left', 'center', 'right'), true) ? $align : 'left',
        );
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>|null
     */
    private function sanitize_image_element(array $element): ?array
    {
        $image_url = esc_url_raw((string) ($element['image_url'] ?? ''));

        if ('' === $image_url) {
            return null;
        }

        $aspect = sanitize_key((string) ($element['aspect'] ?? 'landscape'));

        return array(
            'type'      => 'image',
            'image_url' => $image_url,
            'alt'       => sanitize_text_field((string) ($element['alt'] ?? '')),
            'aspect'    => in_array($aspect, array('portrait', 'square', 'landscape'), true) ? $aspect : 'landscape',
        );
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>|null
     */
    private function sanitize_icon_list_element(array $element): ?array
    {
        $items = array();

        foreach (array_slice((array) ($element['items'] ?? array()), 0, self::MAX_ICON_LIST_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $text = sanitize_text_field((string) ($item['text'] ?? ''));
            $icon = sanitize_text_field((string) ($item['icon'] ?? 'fas fa-check-circle'));

            if ('' === $text) {
                continue;
            }

            $items[] = array(
                'text' => $text,
                'icon' => '' !== $icon ? $icon : 'fas fa-check-circle',
            );
        }

        return array() === $items ? null : array(
            'type'  => 'icon_list',
            'items' => $items,
        );
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>
     */
    private function sanitize_spacer_element(array $element): array
    {
        $size = sanitize_key((string) ($element['size'] ?? 'medium'));

        return array(
            'type' => 'spacer',
            'size' => in_array($size, array('small', 'medium', 'large'), true) ? $size : 'medium',
        );
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>|null
     */
    private function sanitize_faq_element(array $element): ?array
    {
        $items = array();

        foreach (array_slice((array) ($element['items'] ?? array()), 0, self::MAX_FAQ_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = sanitize_text_field((string) ($item['question'] ?? ''));
            $answer = sanitize_textarea_field((string) ($item['answer'] ?? ''));

            if ('' === $question || '' === $answer) {
                continue;
            }

            $items[] = array(
                'question' => $question,
                'answer'   => $answer,
            );
        }

        return array() === $items ? null : array(
            'type'  => 'faq',
            'items' => $items,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function save_preset(array $payload)
    {
        $validated = $this->validate_layout_payload($payload);

        if (is_wp_error($validated)) {
            return $validated;
        }

        $presets = $this->get_presets();
        $slug_base = sanitize_title($validated['title']);

        if ('' === $slug_base) {
            $slug_base = 'preset';
        }

        $slug = $slug_base;
        $suffix = 2;

        while (isset($presets[$slug])) {
            $slug = $slug_base . '-' . $suffix;
            $suffix++;
        }

        $validated['slug'] = $slug;
        $validated['created_at'] = current_time('mysql');

        $presets[$slug] = $validated;
        update_option(ZHD_EB_OPTION_AI_PRESETS, $presets, false);

        return $validated;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function get_presets(): array
    {
        $presets = get_option(ZHD_EB_OPTION_AI_PRESETS, array());

        return is_array($presets) ? $presets : array();
    }

    public function get_schema_json(): string
    {
        return (string) wp_json_encode($this->get_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function get_example_payload_json(): string
    {
        return (string) wp_json_encode(
            array(
                'title'       => 'Premium biotech supplement landing flow',
                'description' => 'Example structured payload for an AI-generated Elementor-ready preset.',
                'source'      => 'openai',
                'sections'    => array(
                    array(
                        'layout'  => 'two_left',
                        'columns' => array(
                            array(
                                'elements' => array(
                                    array(
                                        'type'  => 'heading',
                                        'text'  => 'Clinically grounded nootropic support with cleaner conversion structure.',
                                        'level' => 'h1',
                                        'size'  => 'xl',
                                    ),
                                    array(
                                        'type' => 'text',
                                        'text' => 'Use the uploaded references as a premium design starting point, then simplify the message and emphasize the core CTA.',
                                    ),
                                    array(
                                        'type'  => 'button',
                                        'text'  => 'Start your subscription',
                                        'url'   => 'https://example.com',
                                        'style' => 'primary',
                                        'align' => 'left',
                                    ),
                                ),
                            ),
                            array(
                                'elements' => array(
                                    array(
                                        'type'      => 'image',
                                        'image_url' => 'https://example.com/hero.jpg',
                                        'alt'       => 'Supplement bottle hero shot',
                                        'aspect'    => 'portrait',
                                    ),
                                ),
                            ),
                        ),
                    ),
                    array(
                        'layout'  => 'three_equal',
                        'columns' => array(
                            array(
                                'elements' => array(
                                    array(
                                        'type'  => 'icon_list',
                                        'items' => array(
                                            array(
                                                'text' => 'Third-party tested',
                                                'icon' => 'fas fa-flask',
                                            ),
                                            array(
                                                'text' => 'Fast dispatch',
                                                'icon' => 'fas fa-truck-fast',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                            array(
                                'elements' => array(
                                    array(
                                        'type'  => 'icon_list',
                                        'items' => array(
                                            array(
                                                'text' => 'Transparent ingredients',
                                                'icon' => 'fas fa-leaf',
                                            ),
                                            array(
                                                'text' => 'Secure checkout',
                                                'icon' => 'fas fa-lock',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                            array(
                                'elements' => array(
                                    array(
                                        'type'  => 'icon_list',
                                        'items' => array(
                                            array(
                                                'text' => '30-day guarantee',
                                                'icon' => 'fas fa-shield-heart',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function is_generation_configured(): bool
    {
        $plugin_settings = $this->settings->get_plugin_settings();

        return '' !== $this->settings->get_openai_api_key() || '' !== (string) ($plugin_settings['openai_proxy_url'] ?? '');
    }

    public function get_generation_configuration_label(): string
    {
        $plugin_settings = $this->settings->get_plugin_settings();

        if ('' !== (string) ($plugin_settings['openai_proxy_url'] ?? '')) {
            return __('Proxy endpoint configured', ZHD_EB_TEXT_DOMAIN);
        }

        if ('' !== $this->settings->get_openai_api_key()) {
            return __('Direct OpenAI API key configured', ZHD_EB_TEXT_DOMAIN);
        }

        return __('Not configured', ZHD_EB_TEXT_DOMAIN);
    }

    /**
     * @return array<string, string>
     */
    public function get_available_models(): array
    {
        return array(
            'gpt-5.5' => __('GPT-5.5 (Latest flagship)', ZHD_EB_TEXT_DOMAIN),
            'gpt-5.5-pro' => __('GPT-5.5 Pro', ZHD_EB_TEXT_DOMAIN),
            'gpt-5.4' => __('GPT-5.4', ZHD_EB_TEXT_DOMAIN),
            'gpt-5.4-mini' => __('GPT-5.4 mini', ZHD_EB_TEXT_DOMAIN),
            'gpt-5.4-nano' => __('GPT-5.4 nano', ZHD_EB_TEXT_DOMAIN),
            'gpt-5.2-chat-latest' => __('GPT-5.2 Chat Latest', ZHD_EB_TEXT_DOMAIN),
            'gpt-5.1' => __('GPT-5.1', ZHD_EB_TEXT_DOMAIN),
            'gpt-5-mini' => __('GPT-5 mini', ZHD_EB_TEXT_DOMAIN),
            'gpt-4.1' => __('GPT-4.1', ZHD_EB_TEXT_DOMAIN),
            'gpt-4.1-mini' => __('GPT-4.1 mini', ZHD_EB_TEXT_DOMAIN),
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|WP_Error
     */
    public function generate_preset_from_brief(array $input, array $files = array())
    {
        if (! $this->is_generation_configured()) {
            return new WP_Error('zhd_ai_not_configured', __('OpenAI generation is not configured yet. Add an API key or a proxy endpoint in the plugin settings first.', ZHD_EB_TEXT_DOMAIN));
        }

        $brief = sanitize_textarea_field((string) ($input['brief'] ?? ''));
        $business_context = sanitize_textarea_field((string) ($input['business_context'] ?? ''));
        $cta_goal = sanitize_text_field((string) ($input['cta_goal'] ?? ''));
        $visual_direction = sanitize_textarea_field((string) ($input['visual_direction'] ?? ''));
        $model = $this->resolve_model((string) ($input['generation_model'] ?? ''));
        $reference_images = $this->prepare_reference_images($files['reference_screenshots'] ?? null);

        if (is_wp_error($reference_images)) {
            return $reference_images;
        }

        if ('' === $brief && array() === $reference_images) {
            return new WP_Error('zhd_ai_missing_input', __('Please provide a design brief, screenshot references, or both before generating a preset.', ZHD_EB_TEXT_DOMAIN));
        }

        $request_body = array(
            'model' => $model,
            'input' => array(
                array(
                    'role'    => 'system',
                    'content' => array(
                        array(
                            'type' => 'input_text',
                            'text' => $this->get_system_prompt(),
                        ),
                    ),
                ),
                array(
                    'role'    => 'user',
                    'content' => $this->build_user_content($brief, $business_context, $cta_goal, $visual_direction, $reference_images),
                ),
            ),
            'text'  => array(
                'format' => array(
                    'type'        => 'json_schema',
                    'name'        => 'zhd_elementor_layout',
                    'description' => 'Structured Elementor-friendly layout payload for ZHD Elementor Blocks',
                    'strict'      => true,
                    'schema'      => $this->get_generation_json_schema(),
                ),
            ),
        );

        $json = $this->request_structured_generation($request_body);

        if (is_wp_error($json)) {
            return $json;
        }

        $output_text = $this->extract_output_text($json);

        if ('' === $output_text) {
            return new WP_Error('zhd_ai_missing_output', __('OpenAI generation returned no structured output text.', ZHD_EB_TEXT_DOMAIN));
        }

        $payload = json_decode($output_text, true);

        if (! is_array($payload)) {
            return new WP_Error('zhd_ai_invalid_json', __('OpenAI generation returned invalid JSON.', ZHD_EB_TEXT_DOMAIN));
        }

        $payload['source'] = 'openai';
        $save_result = $this->save_preset($payload);

        if (is_wp_error($save_result)) {
            return $save_result;
        }

        $save_result['meta'] = array(
            'model'            => $model,
            'response_id'      => sanitize_text_field((string) ($json['id'] ?? '')),
            'status'           => sanitize_text_field((string) ($json['status'] ?? 'completed')),
            'reference_images' => count($reference_images),
        );

        return $save_result;
    }

    /**
     * @param array<string, mixed> $request_body
     * @return array<string, mixed>|WP_Error
     */
    private function request_structured_generation(array $request_body)
    {
        $response = wp_remote_post(
            $this->get_endpoint(),
            array(
                'timeout' => 45,
                'headers' => $this->get_request_headers(),
                'body'    => wp_json_encode($request_body),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('zhd_ai_request_failed', $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            return new WP_Error(
                'zhd_ai_http_error',
                sprintf(
                    /* translators: 1: HTTP status code, 2: API error message */
                    __('OpenAI generation failed (%1$s): %2$s', ZHD_EB_TEXT_DOMAIN),
                    (string) $status,
                    $this->extract_api_error_message($json)
                )
            );
        }

        if (! is_array($json)) {
            return new WP_Error('zhd_ai_invalid_response', __('OpenAI generation returned an unreadable response body.', ZHD_EB_TEXT_DOMAIN));
        }

        return $json;
    }

    /**
     * @param array<int, array<string, string>> $reference_images
     * @return array<int, array<string, string|int>>
     */
    private function build_user_content(string $brief, string $business_context, string $cta_goal, string $visual_direction, array $reference_images): array
    {
        $content = array(
            array(
                'type' => 'input_text',
                'text' => $this->build_user_prompt($brief, $business_context, $cta_goal, $visual_direction, count($reference_images)),
            ),
        );

        foreach ($reference_images as $index => $reference_image) {
            $content[] = array(
                'type'      => 'input_image',
                'image_url' => $reference_image['data_url'],
                'detail'    => 'high',
            );

            $content[] = array(
                'type' => 'input_text',
                'text' => sprintf(
                    /* translators: 1: image number, 2: file name */
                    __('Reference image %1$d filename: %2$s', ZHD_EB_TEXT_DOMAIN),
                    $index + 1,
                    $reference_image['name']
                ),
            );
        }

        return $content;
    }

    /**
     * @param mixed $uploaded_files
     * @return array<int, array{name: string, mime: string, data_url: string}>|WP_Error
     */
    private function prepare_reference_images($uploaded_files)
    {
        if (! is_array($uploaded_files) || ! isset($uploaded_files['name']) || ! is_array($uploaded_files['name'])) {
            return array();
        }

        $names = $uploaded_files['name'];
        $tmp_names = $uploaded_files['tmp_name'] ?? array();
        $errors = $uploaded_files['error'] ?? array();
        $sizes = $uploaded_files['size'] ?? array();

        $reference_images = array();

        foreach ($names as $index => $name) {
            $name = sanitize_file_name((string) $name);
            $tmp_name = (string) ($tmp_names[$index] ?? '');
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            $size = (int) ($sizes[$index] ?? 0);

            if (UPLOAD_ERR_NO_FILE === $error || '' === $name) {
                continue;
            }

            if (UPLOAD_ERR_OK !== $error) {
                return new WP_Error('zhd_ai_upload_error', __('One of the uploaded screenshots could not be processed. Please try again with fresh image files.', ZHD_EB_TEXT_DOMAIN));
            }

            if ($size <= 0 || $size > self::MAX_REFERENCE_IMAGE_BYTES) {
                return new WP_Error(
                    'zhd_ai_upload_too_large',
                    sprintf(
                        /* translators: %s: size limit */
                        __('Each screenshot must be smaller than %s.', ZHD_EB_TEXT_DOMAIN),
                        size_format(self::MAX_REFERENCE_IMAGE_BYTES)
                    )
                );
            }

            if ('' === $tmp_name || ! file_exists($tmp_name)) {
                return new WP_Error('zhd_ai_upload_missing_file', __('A screenshot upload was missing its temporary file. Please retry the upload.', ZHD_EB_TEXT_DOMAIN));
            }

            $image_info = wp_getimagesize($tmp_name);
            $mime_type = is_array($image_info) && isset($image_info['mime']) ? (string) $image_info['mime'] : '';

            if (! in_array($mime_type, self::ALLOWED_REFERENCE_IMAGE_MIME_TYPES, true)) {
                return new WP_Error('zhd_ai_upload_invalid_type', __('Only PNG, JPG, and WebP screenshots are supported right now.', ZHD_EB_TEXT_DOMAIN));
            }

            $bytes = file_get_contents($tmp_name);

            if (false === $bytes || '' === $bytes) {
                return new WP_Error('zhd_ai_upload_unreadable', __('One of the screenshot files could not be read. Please retry with a different image export.', ZHD_EB_TEXT_DOMAIN));
            }

            $reference_images[] = array(
                'name'     => $name,
                'mime'     => $mime_type,
                'data_url' => 'data:' . $mime_type . ';base64,' . base64_encode($bytes),
            );

            if (count($reference_images) >= self::MAX_REFERENCE_IMAGES) {
                break;
            }
        }

        return $reference_images;
    }

    public function get_reference_limits_description(): string
    {
        return sprintf(
            /* translators: 1: image count, 2: size limit */
            __('Upload up to %1$d screenshots. Supported formats: PNG, JPG, WebP. Max file size per screenshot: %2$s.', ZHD_EB_TEXT_DOMAIN),
            self::MAX_REFERENCE_IMAGES,
            size_format(self::MAX_REFERENCE_IMAGE_BYTES)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get_generation_json_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('title', 'description', 'source', 'sections'),
            'properties'           => array(
                'title'       => array(
                    'type' => 'string',
                ),
                'description' => array(
                    'type' => 'string',
                ),
                'source'      => array(
                    'type' => 'string',
                    'enum' => array('openai'),
                ),
                'sections'    => array(
                    'type'  => 'array',
                    'items' => $this->get_generation_section_schema(),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_generation_section_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('layout', 'columns'),
            'properties'           => array(
                'layout'  => array(
                    'type' => 'string',
                    'enum' => self::ALLOWED_LAYOUTS,
                ),
                'columns' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => array('elements'),
                        'properties'           => array(
                            'elements' => array(
                                'type'  => 'array',
                                'items' => array(
                                    'anyOf' => array(
                                        $this->get_heading_element_schema(),
                                        $this->get_text_element_schema(),
                                        $this->get_button_element_schema(),
                                        $this->get_image_element_schema(),
                                        $this->get_icon_list_element_schema(),
                                        $this->get_spacer_element_schema(),
                                        $this->get_divider_element_schema(),
                                        $this->get_faq_element_schema(),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_heading_element_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('type', 'text', 'level', 'size'),
            'properties'           => array(
                'type'  => array(
                    'type' => 'string',
                    'enum' => array('heading'),
                ),
                'text'  => array('type' => 'string'),
                'level' => array(
                    'type' => 'string',
                    'enum' => array('h1', 'h2', 'h3', 'h4'),
                ),
                'size'  => array(
                    'type' => 'string',
                    'enum' => array('xl', 'large', 'medium', 'small'),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_text_element_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('type', 'text'),
            'properties'           => array(
                'type' => array(
                    'type' => 'string',
                    'enum' => array('text'),
                ),
                'text' => array('type' => 'string'),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_button_element_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('type', 'text', 'url', 'style', 'align'),
            'properties'           => array(
                'type'  => array(
                    'type' => 'string',
                    'enum' => array('button'),
                ),
                'text'  => array('type' => 'string'),
                'url'   => array('type' => 'string'),
                'style' => array(
                    'type' => 'string',
                    'enum' => array('primary', 'secondary', 'link'),
                ),
                'align' => array(
                    'type' => 'string',
                    'enum' => array('left', 'center', 'right'),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_image_element_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('type', 'image_url', 'alt', 'aspect'),
            'properties'           => array(
                'type'      => array(
                    'type' => 'string',
                    'enum' => array('image'),
                ),
                'image_url' => array('type' => 'string'),
                'alt'       => array('type' => 'string'),
                'aspect'    => array(
                    'type' => 'string',
                    'enum' => array('portrait', 'square', 'landscape'),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_icon_list_element_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('type', 'items'),
            'properties'           => array(
                'type'  => array(
                    'type' => 'string',
                    'enum' => array('icon_list'),
                ),
                'items' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => array('text', 'icon'),
                        'properties'           => array(
                            'text' => array('type' => 'string'),
                            'icon' => array('type' => 'string'),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_spacer_element_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('type', 'size'),
            'properties'           => array(
                'type' => array(
                    'type' => 'string',
                    'enum' => array('spacer'),
                ),
                'size' => array(
                    'type' => 'string',
                    'enum' => array('small', 'medium', 'large'),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_divider_element_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('type'),
            'properties'           => array(
                'type' => array(
                    'type' => 'string',
                    'enum' => array('divider'),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_faq_element_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('type', 'items'),
            'properties'           => array(
                'type'  => array(
                    'type' => 'string',
                    'enum' => array('faq'),
                ),
                'items' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => array('question', 'answer'),
                        'properties'           => array(
                            'question' => array('type' => 'string'),
                            'answer'   => array('type' => 'string'),
                        ),
                    ),
                ),
            ),
        );
    }

    public function get_preferred_model(): string
    {
        $settings = $this->settings->get_plugin_settings();

        return $this->resolve_model((string) ($settings['openai_model'] ?? self::DEFAULT_MODEL));
    }

    private function resolve_model(string $requested_model = ''): string
    {
        $requested_model = sanitize_text_field($requested_model);
        $available_models = $this->get_available_models();

        if ('' !== $requested_model) {
            if (isset($available_models[$requested_model])) {
                return $requested_model;
            }

            return $requested_model;
        }

        $settings = $this->settings->get_plugin_settings();
        $preferred = sanitize_text_field((string) ($settings['openai_model'] ?? self::DEFAULT_MODEL));

        if (isset($available_models[$preferred])) {
            return $preferred;
        }

        return self::DEFAULT_MODEL;
    }

    private function get_endpoint(): string
    {
        $settings = $this->settings->get_plugin_settings();
        $proxy = (string) ($settings['openai_proxy_url'] ?? '');

        return '' !== $proxy ? $proxy : self::OPENAI_RESPONSES_URL;
    }

    /**
     * @return array<string, string>
     */
    private function get_request_headers(): array
    {
        $headers = array(
            'Content-Type' => 'application/json',
        );

        $api_key = $this->settings->get_openai_api_key();

        if ('' !== $api_key) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }

        return $headers;
    }

    private function get_system_prompt(): string
    {
        return implode(
            "\n",
            array(
                'You generate conversion-focused layout presets for a WordPress plugin that compiles them into native Elementor templates.',
                'Return only a structured JSON object matching the provided schema.',
                'Do not use plugin-specific widgets unless the schema explicitly asks for them.',
                'Build layouts using sections, columns, and Elementor-friendly elements like heading, text, button, image, icon_list, spacer, divider, and faq.',
                'Prefer 2 to 6 sections unless the brief clearly needs fewer.',
                'When screenshots are provided, use them as layout and hierarchy references from top to bottom.',
                'Preserve the overall section sequence and information architecture, but improve the copy for CRO clarity.',
                'If something in a screenshot is too bespoke to reproduce exactly, simplify it into the closest supported Elementor-friendly structure.',
                'Set source to openai.',
            )
        );
    }

    private function build_user_prompt(string $brief, string $business_context, string $cta_goal, string $visual_direction, int $reference_image_count): string
    {
        return wp_json_encode(
            array(
                'task'             => 0 === $reference_image_count ? 'Generate a native-Elementor-ready layout preset from this brief.' : 'Generate a native-Elementor-ready layout preset using the brief and uploaded screenshot references.',
                'brief'            => $brief,
                'business_context' => $business_context,
                'cta_goal'         => $cta_goal,
                'visual_direction' => $visual_direction,
                'reference_images' => $reference_image_count,
                'rules'            => array(
                    'Do not output raw Elementor JSON.',
                    'Do not invent unsupported element types.',
                    'Use sections and columns to express major layout changes.',
                    'Use valid URLs or empty strings for URL fields.',
                    'Assume visual defaults should inherit from Elementor global styles or the active theme.',
                    'Treat screenshots as inspiration for structure and rhythm, not a requirement for pixel-perfect cloning.',
                ),
            ),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) ?: $brief;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extract_output_text(array $response): string
    {
        if (! empty($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        $output = $response['output'] ?? array();

        if (! is_array($output)) {
            return '';
        }

        foreach ($output as $item) {
            if (! is_array($item) || ! is_array($item['content'] ?? null)) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (! is_array($content)) {
                    continue;
                }

                if (isset($content['text']) && is_string($content['text'])) {
                    return $content['text'];
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function extract_api_error_message(?array $json): string
    {
        if (is_array($json['error'] ?? null) && is_string($json['error']['message'] ?? null)) {
            return $json['error']['message'];
        }

        return __('Unknown API error', ZHD_EB_TEXT_DOMAIN);
    }
}
