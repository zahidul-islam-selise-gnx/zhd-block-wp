<?php
/**
 * Elementor widget registration.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

use ZHD\ElementorBlocks\Widgets\AICopilot;

final class WidgetsLoader
{
    /**
     * @var array<int, class-string>
     */
    private array $widget_classes = array(
        AICopilot::class,
    );

    public function __construct(private readonly Dependencies $dependencies)
    {
    }

    public function register_hooks(): void
    {
        if (! $this->dependencies->is_elementor_installed()) {
            return;
        }

        add_action('elementor/elements/categories_registered', array($this, 'register_category'));
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
    }

    public function register_category(\Elementor\Elements_Manager $elements_manager): void
    {
        $elements_manager->add_category(
            'zhd-ai-copilot',
            array(
                'title' => __('ZHD AI Copilot', ZHD_EB_TEXT_DOMAIN),
                'icon'  => 'fa fa-plug',
            )
        );
    }

    public function register_widgets(\Elementor\Widgets_Manager $widgets_manager): void
    {
        foreach ($this->get_widget_manifest() as $widget) {
            if (! $this->dependencies->are_widget_requirements_met($widget['requirements'])) {
                continue;
            }

            $widgets_manager->register(new $widget['class']());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_widget_manifest(): array
    {
        $manifest = array();

        foreach ($this->widget_classes as $class_name) {
            if (! class_exists($class_name)) {
                continue;
            }

            /** @var class-string<\ZHD\ElementorBlocks\Widgets\BaseWidget> $class_name */
            $meta = $class_name::admin_manifest();

            $manifest[] = array(
                'class'        => $class_name,
                'slug'         => $meta['slug'],
                'title'        => $meta['title'],
                'description'  => $meta['description'],
                'requirements' => $meta['requirements'],
                'styles'       => $meta['style_handles'],
                'scripts'      => $meta['script_handles'],
                'available'    => $this->dependencies->are_widget_requirements_met($meta['requirements']),
            );
        }

        return $manifest;
    }
}
