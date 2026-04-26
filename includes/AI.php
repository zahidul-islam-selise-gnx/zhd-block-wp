<?php
/**
 * Safe AI schema and preset storage.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

use WP_Error;

final class AI
{
    private const OPENAI_RESPONSES_URL = 'https://api.openai.com/v1/responses';

    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function get_schema(): array
    {
        return array(
            'schema_version' => 1,
            'title'          => 'ZHD Safe Layout Schema',
            'description'    => 'Structured layout payload for AI-assisted design generation. This schema is intentionally limited to known ZHD widgets and sanitized settings.',
            'output_shape'   => array(
                'title'       => 'string',
                'description' => 'string',
                'source'      => 'manual|openai|import',
                'blocks'      => 'array<block>',
            ),
            'block_shape'    => array(
                'widget'   => 'allowed ZHD widget slug',
                'settings' => 'sanitized per widget',
            ),
            'widgets'        => array(
                'zhd_hero_cro' => array(
                    'label'       => 'Hero CRO',
                    'description' => 'Primary conversion hero section.',
                    'settings'    => array(
                        'eyebrow'              => 'string',
                        'heading'              => 'string',
                        'subheading'           => 'string',
                        'button_text'          => 'string',
                        'button_url'           => 'url',
                        'background_image_url' => 'url',
                        'overlay_color'        => 'string',
                    ),
                ),
                'zhd_product_buy_box' => array(
                    'label'       => 'Product Buy Box',
                    'description' => 'WooCommerce-aware conversion card.',
                    'settings'    => array(
                        'product_source'    => 'context|manual',
                        'manual_product_id' => 'integer',
                        'override_title'    => 'string',
                        'guarantee_text'    => 'string',
                        'stock_label'       => 'string',
                        'show_description'  => 'boolean',
                        'trust_badges'      => 'array<string>',
                    ),
                ),
                'zhd_trust_badges' => array(
                    'label'       => 'Trust Badges',
                    'description' => 'Credibility items with icon labels.',
                    'settings'    => array(
                        'items'   => 'array<{label:string,icon:string}>',
                        'columns' => '2|3|4',
                    ),
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
        $blocks = $payload['blocks'] ?? array();

        if ('' === $title) {
            return new WP_Error('zhd_ai_missing_title', __('AI preset validation failed: title is required.', ZHD_EB_TEXT_DOMAIN));
        }

        if (! is_array($blocks) || array() === $blocks) {
            return new WP_Error('zhd_ai_missing_blocks', __('AI preset validation failed: at least one block is required.', ZHD_EB_TEXT_DOMAIN));
        }

        if (! in_array($source, array('manual', 'openai', 'import'), true)) {
            $source = 'manual';
        }

        $sanitized_blocks = array();

        foreach (array_slice($blocks, 0, 25) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $widget = sanitize_key((string) ($block['widget'] ?? ''));
            $settings = is_array($block['settings'] ?? null) ? $block['settings'] : array();
            $sanitized = $this->sanitize_widget_settings($widget, $settings);

            if (null === $sanitized) {
                continue;
            }

            $sanitized_blocks[] = array(
                'widget'   => $widget,
                'settings' => $sanitized,
            );
        }

        if (array() === $sanitized_blocks) {
            return new WP_Error('zhd_ai_no_supported_blocks', __('AI preset validation failed: no supported ZHD blocks were found.', ZHD_EB_TEXT_DOMAIN));
        }

        return array(
            'title'       => $title,
            'description' => $description,
            'source'      => $source,
            'blocks'      => $sanitized_blocks,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sanitize_widget_settings(string $widget, array $settings): ?array
    {
        switch ($widget) {
            case 'zhd_hero_cro':
                return array(
                    'eyebrow'              => sanitize_text_field((string) ($settings['eyebrow'] ?? '')),
                    'heading'              => sanitize_textarea_field((string) ($settings['heading'] ?? '')),
                    'subheading'           => sanitize_textarea_field((string) ($settings['subheading'] ?? '')),
                    'button_text'          => sanitize_text_field((string) ($settings['button_text'] ?? '')),
                    'button_url'           => esc_url_raw((string) ($settings['button_url'] ?? '')),
                    'background_image_url' => esc_url_raw((string) ($settings['background_image_url'] ?? '')),
                    'overlay_color'        => sanitize_text_field((string) ($settings['overlay_color'] ?? '')),
                );

            case 'zhd_product_buy_box':
                $trust_badges = array();
                foreach ((array) ($settings['trust_badges'] ?? array()) as $badge) {
                    $badge = sanitize_text_field((string) $badge);
                    if ('' !== $badge) {
                        $trust_badges[] = $badge;
                    }
                }

                return array(
                    'product_source'    => 'manual' === ($settings['product_source'] ?? 'context') ? 'manual' : 'context',
                    'manual_product_id' => absint($settings['manual_product_id'] ?? 0),
                    'override_title'    => sanitize_text_field((string) ($settings['override_title'] ?? '')),
                    'guarantee_text'    => sanitize_text_field((string) ($settings['guarantee_text'] ?? '')),
                    'stock_label'       => sanitize_text_field((string) ($settings['stock_label'] ?? '')),
                    'show_description'  => ! empty($settings['show_description']),
                    'trust_badges'      => array_slice($trust_badges, 0, 8),
                );

            case 'zhd_trust_badges':
                $items = array();
                foreach ((array) ($settings['items'] ?? array()) as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $label = sanitize_text_field((string) ($item['label'] ?? ''));
                    $icon = sanitize_text_field((string) ($item['icon'] ?? 'fas fa-check-circle'));

                    if ('' === $label) {
                        continue;
                    }

                    $items[] = array(
                        'label' => $label,
                        'icon'  => $icon,
                    );
                }

                return array(
                    'items'   => array_slice($items, 0, 8),
                    'columns' => in_array((string) ($settings['columns'] ?? '3'), array('2', '3', '4'), true) ? (string) $settings['columns'] : '3',
                );
        }

        return null;
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
                'title'       => 'High-converting biotech hero stack',
                'description' => 'Example structured payload for a safe AI-generated landing section.',
                'source'      => 'openai',
                'blocks'      => array(
                    array(
                        'widget'   => 'zhd_hero_cro',
                        'settings' => array(
                            'eyebrow'              => 'Science-backed conversion system',
                            'heading'              => 'Launch faster with reusable CRO blocks.',
                            'subheading'           => 'Use the shared ZHD widgets to keep copy, design, and trust signals consistent.',
                            'button_text'          => 'See the block system',
                            'button_url'           => 'https://example.com',
                            'background_image_url' => 'https://example.com/hero.jpg',
                            'overlay_color'        => 'rgba(15, 23, 42, 0.78)',
                        ),
                    ),
                    array(
                        'widget'   => 'zhd_trust_badges',
                        'settings' => array(
                            'columns' => '3',
                            'items'   => array(
                                array(
                                    'label' => 'Third-party tested',
                                    'icon'  => 'fas fa-flask',
                                ),
                                array(
                                    'label' => 'Secure checkout',
                                    'icon'  => 'fas fa-lock',
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
     * @param array<string, mixed> $input
     * @return array<string, mixed>|WP_Error
     */
    public function generate_preset_from_brief(array $input)
    {
        if (! $this->is_generation_configured()) {
            return new WP_Error('zhd_ai_not_configured', __('OpenAI generation is not configured yet. Add an API key or a proxy endpoint in the Design System settings first.', ZHD_EB_TEXT_DOMAIN));
        }

        $brief = sanitize_textarea_field((string) ($input['brief'] ?? ''));
        $business_context = sanitize_textarea_field((string) ($input['business_context'] ?? ''));
        $cta_goal = sanitize_text_field((string) ($input['cta_goal'] ?? ''));

        if ('' === $brief) {
            return new WP_Error('zhd_ai_missing_brief', __('Please provide a design brief before generating a preset.', ZHD_EB_TEXT_DOMAIN));
        }

        $request_body = array(
            'model' => $this->get_model(),
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
                    'content' => array(
                        array(
                            'type' => 'input_text',
                            'text' => $this->build_user_prompt($brief, $business_context, $cta_goal),
                        ),
                    ),
                ),
            ),
            'text'  => array(
                'format' => array(
                    'type'        => 'json_schema',
                    'name'        => 'zhd_safe_layout',
                    'description' => 'Structured layout payload for ZHD Elementor Blocks',
                    'strict'      => true,
                    'schema'      => $this->get_generation_json_schema(),
                ),
            ),
        );

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
        $body   = (string) wp_remote_retrieve_body($response);
        $json   = json_decode($body, true);

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
            'model'       => $this->get_model(),
            'response_id' => sanitize_text_field((string) ($json['id'] ?? '')),
            'status'      => sanitize_text_field((string) ($json['status'] ?? 'completed')),
        );

        return $save_result;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_generation_json_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('title', 'description', 'source', 'blocks'),
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
                'blocks'      => array(
                    'type'  => 'array',
                    'items' => array(
                        'anyOf' => array(
                            $this->get_hero_block_schema(),
                            $this->get_buy_box_block_schema(),
                            $this->get_trust_badges_block_schema(),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_hero_block_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('widget', 'settings'),
            'properties'           => array(
                'widget'   => array(
                    'type' => 'string',
                    'enum' => array('zhd_hero_cro'),
                ),
                'settings' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => array('eyebrow', 'heading', 'subheading', 'button_text', 'button_url', 'background_image_url', 'overlay_color'),
                    'properties'           => array(
                        'eyebrow'              => array('type' => 'string'),
                        'heading'              => array('type' => 'string'),
                        'subheading'           => array('type' => 'string'),
                        'button_text'          => array('type' => 'string'),
                        'button_url'           => array('type' => 'string'),
                        'background_image_url' => array('type' => 'string'),
                        'overlay_color'        => array('type' => 'string'),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_buy_box_block_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('widget', 'settings'),
            'properties'           => array(
                'widget'   => array(
                    'type' => 'string',
                    'enum' => array('zhd_product_buy_box'),
                ),
                'settings' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => array('product_source', 'manual_product_id', 'override_title', 'guarantee_text', 'stock_label', 'show_description', 'trust_badges'),
                    'properties'           => array(
                        'product_source'    => array(
                            'type' => 'string',
                            'enum' => array('context', 'manual'),
                        ),
                        'manual_product_id' => array('type' => 'integer'),
                        'override_title'    => array('type' => 'string'),
                        'guarantee_text'    => array('type' => 'string'),
                        'stock_label'       => array('type' => 'string'),
                        'show_description'  => array('type' => 'boolean'),
                        'trust_badges'      => array(
                            'type'  => 'array',
                            'items' => array('type' => 'string'),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_trust_badges_block_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array('widget', 'settings'),
            'properties'           => array(
                'widget'   => array(
                    'type' => 'string',
                    'enum' => array('zhd_trust_badges'),
                ),
                'settings' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => array('items', 'columns'),
                    'properties'           => array(
                        'items'   => array(
                            'type'  => 'array',
                            'items' => array(
                                'type'                 => 'object',
                                'additionalProperties' => false,
                                'required'             => array('label', 'icon'),
                                'properties'           => array(
                                    'label' => array('type' => 'string'),
                                    'icon'  => array('type' => 'string'),
                                ),
                            ),
                        ),
                        'columns' => array(
                            'type' => 'string',
                            'enum' => array('2', '3', '4'),
                        ),
                    ),
                ),
            ),
        );
    }

    private function get_model(): string
    {
        $settings = $this->settings->get_plugin_settings();

        return (string) ($settings['openai_model'] ?? 'gpt-5.5');
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
                'You generate safe, conversion-focused layout presets for a WordPress Elementor plugin.',
                'Return only a structured JSON object matching the provided schema.',
                'Use only supported ZHD widgets: zhd_hero_cro, zhd_product_buy_box, zhd_trust_badges.',
                'Prefer 2 to 3 blocks unless the brief clearly needs less.',
                'Keep the layout practical for marketing pages and product pages.',
                'If the brief is not product-specific, avoid the Product Buy Box widget.',
                'Use concise, high-converting copy and keep trust badges specific and believable.',
                'Set source to openai.',
            )
        );
    }

    private function build_user_prompt(string $brief, string $business_context, string $cta_goal): string
    {
        $tokens = $this->settings->get_tokens();

        return wp_json_encode(
            array(
                'task'             => 'Generate a safe ZHD layout preset from this brief.',
                'brief'            => $brief,
                'business_context' => $business_context,
                'cta_goal'         => $cta_goal,
                'design_tokens'    => array(
                    'primary_color'   => $tokens['primary_color'],
                    'secondary_color' => $tokens['secondary_color'],
                    'accent_color'    => $tokens['accent_color'],
                    'font_heading'    => $tokens['font_heading'],
                    'font_body'       => $tokens['font_body'],
                ),
                'rules'            => array(
                    'Do not invent unsupported widgets.',
                    'Do not output raw Elementor JSON.',
                    'Use valid URLs or empty strings for URL fields.',
                    'Use manual_product_id 0 when no manual product is intended.',
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
