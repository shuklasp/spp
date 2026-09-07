<?php

namespace AppMod\SPPDocs\ReadingTime;

class ReadingTimeModule
{
    /**
     * Hook into document rendering data alter.
     */
    public function hook_document_render_alter(&$data, $context = null)
    {
        $content = $data['content'] ?? '';
        $cleanText = strip_tags($content);
        $wordCount = str_word_count($cleanText);
        $minutes = max(1, (int)ceil($wordCount / 200));

        $data['word_count'] = $wordCount;
        $data['reading_time'] = "{$minutes} min read";
        if (isset($data['frontmatter']) && is_array($data['frontmatter'])) {
            $data['frontmatter']['word_count'] = $wordCount;
            $data['frontmatter']['reading_time'] = "{$minutes} min read";
        }
    }

    /**
     * Hook to provide block info.
     */
    public function hook_block_info()
    {
        return [
            'reading_time_badge' => [
                'title' => 'Reading Time Badge',
                'description' => 'Displays reading duration and total word count badge',
            ]
        ];
    }
}

$instance = new ReadingTimeModule();
\App\SPPDocs\Services\ModuleManager::registerModuleInstance('spp', 'reading_time', $instance);
return $instance;