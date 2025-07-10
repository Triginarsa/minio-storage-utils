<?php

namespace Triginarsa\MinioStorageUtils\Naming;

class SlugNamer implements NamerInterface
{
    public function generateName(string $originalName, string $content, string $extension): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = $this->createSlug($name);
        $timestamp = time();
        
        return $slug . '-' . $timestamp . '.' . ltrim($extension, '.');
    }

    private function createSlug(string $string): string
    {
        $slug = strtolower(trim($string));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
} 