<?php

namespace Triginarsa\MinioStorageUtils\Processors;

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\MP4;
use FFMpeg\Format\Video\WebM;
use FFMpeg\Format\Video\X264;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Filters\Video\VideoFilters;
use Psr\Log\LoggerInterface;
use Triginarsa\MinioStorageUtils\Exceptions\UploadException;

class VideoProcessor
{
    private ?FFMpeg $ffmpeg = null;
    private LoggerInterface $logger;
    private array $videoTypes;
    private bool $ffmpegAvailable = false;

    public function __construct(LoggerInterface $logger, array $ffmpegConfig = [])
    {
        $this->logger = $logger;
        
        // Default FFmpeg configuration
        $defaultConfig = [
            'ffmpeg.binaries' => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe',
            'timeout' => 3600,
            'ffmpeg.threads' => 12,
        ];
        
        $config = array_merge($defaultConfig, $ffmpegConfig);
        
        try {
            // Check if php-ffmpeg package is installed
            if (!class_exists('FFMpeg\FFMpeg')) {
                throw new \Exception('php-ffmpeg/php-ffmpeg package is not installed');
            }
            
            $this->ffmpeg = FFMpeg::create($config);
            $this->ffmpegAvailable = true;
            $this->logger->info('FFmpeg initialized successfully', ['config' => $config]);
        } catch (\Exception $e) {
            $this->ffmpegAvailable = false;
            $this->logger->warning('FFmpeg not available', [
                'error' => $e->getMessage(),
                'config' => $config,
                'php_ffmpeg_installed' => class_exists('FFMpeg\FFMpeg'),
                'suggestion' => 'Install php-ffmpeg/php-ffmpeg package and ensure FFmpeg is installed on the system'
            ]);
        }

        $this->videoTypes = [
            'video/mp4',
            'video/avi',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/x-flv',
            'video/webm',
            'video/3gpp',
            'video/x-matroska',
        ];
    }

    public function isVideo(string $mimeType): bool
    {
        return in_array($mimeType, $this->videoTypes) || strpos($mimeType, 'video/') === 0;
    }

    public function isFFmpegAvailable(): bool
    {
        return $this->ffmpegAvailable;
    }

    private function requireFFmpeg(string $operation): void
    {
        if (!$this->ffmpegAvailable) {
            throw new UploadException(
                "FFmpeg is required for {$operation} but is not installed or accessible. " .
                "Please install FFmpeg to use video processing features. " .
                "Video uploads without processing are still supported.",
                ['operation' => $operation, 'ffmpeg_available' => false]
            );
        }
    }

    public function process(string $inputPath, string $outputPath, array $options): string
    {
        $this->requireFFmpeg('video processing');

        $this->logger->info('Video processing started', [
            'input' => $inputPath,
            'output' => $outputPath,
            'options' => $options
        ]);

        try {
            $video = $this->ffmpeg->open($inputPath);
            
            // Get video information
            $videoInfo = $this->getVideoInfo($inputPath);
            $this->logger->info('Video info retrieved', $videoInfo);

            // Apply video filters and transformations
            $this->applyVideoFilters($video, $options, $videoInfo);

            // Set output format
            $format = $this->getOutputFormat($options);
            
            // Configure compression settings
            $this->configureCompression($format, $options);

            // Save processed video
            $video->save($format, $outputPath);

            $this->logger->info('Video processing completed successfully', [
                'input' => $inputPath,
                'output' => $outputPath,
                'format' => get_class($format)
            ]);

            return $outputPath;

        } catch (\Exception $e) {
            $this->logger->error('Video processing failed', [
                'input' => $inputPath,
                'output' => $outputPath,
                'error' => $e->getMessage()
            ]);
            
            throw new UploadException(
                "Video processing failed: {$e->getMessage()}",
                ['input' => $inputPath, 'output' => $outputPath],
                $e
            );
        }
    }

    public function createThumbnail(string $inputPath, string $outputPath, array $options): string
    {
        $this->requireFFmpeg('video thumbnail creation');

        $this->logger->info('Video thumbnail creation started', [
            'input' => $inputPath,
            'output' => $outputPath,
            'options' => $options
        ]);

        try {
            $video = $this->ffmpeg->open($inputPath);
            
            // Get frame at specified time or middle of video
            $timeCode = $options['time'] ?? '00:00:05';
            if (is_numeric($timeCode)) {
                $timeCode = TimeCode::fromSeconds($timeCode);
            } else {
                $timeCode = TimeCode::fromString($timeCode);
            }

            $frame = $video->frame($timeCode);
            
            // Apply thumbnail options
            if (isset($options['width']) || isset($options['height'])) {
                $width = $options['width'] ?? null;
                $height = $options['height'] ?? null;
                
                if ($width && $height) {
                    $frame->filters()->resize(new Dimension($width, $height));
                }
            }

            $frame->save($outputPath);

            $this->logger->info('Video thumbnail created successfully', [
                'input' => $inputPath,
                'output' => $outputPath,
                'time' => $timeCode->__toString()
            ]);

            return $outputPath;

        } catch (\Exception $e) {
            $this->logger->error('Video thumbnail creation failed', [
                'input' => $inputPath,
                'output' => $outputPath,
                'error' => $e->getMessage()
            ]);
            
            throw new UploadException(
                "Video thumbnail creation failed: {$e->getMessage()}",
                ['input' => $inputPath, 'output' => $outputPath],
                $e
            );
        }
    }

    public function getVideoInfo(string $videoPath): array
    {
        $this->requireFFmpeg('video information retrieval');

        try {
            $video = $this->ffmpeg->open($videoPath);
            $ffprobe = $video->getFFProbe();
            $videoStream = $ffprobe->streams($videoPath)->videos()->first();
            $audioStream = $ffprobe->streams($videoPath)->audios()->first();
            
            $info = [
                'duration' => $ffprobe->format($videoPath)->get('duration'),
                'bitrate' => $ffprobe->format($videoPath)->get('bit_rate'),
                'size' => $ffprobe->format($videoPath)->get('size'),
                'format' => $ffprobe->format($videoPath)->get('format_name'),
            ];

            if ($videoStream) {
                $info['video'] = [
                    'width' => $videoStream->get('width'),
                    'height' => $videoStream->get('height'),
                    'codec' => $videoStream->get('codec_name'),
                    'bitrate' => $videoStream->get('bit_rate'),
                    'fps' => $videoStream->get('r_frame_rate'),
                    'aspect_ratio' => $videoStream->get('display_aspect_ratio'),
                ];
            }

            if ($audioStream) {
                $info['audio'] = [
                    'codec' => $audioStream->get('codec_name'),
                    'bitrate' => $audioStream->get('bit_rate'),
                    'sample_rate' => $audioStream->get('sample_rate'),
                    'channels' => $audioStream->get('channels'),
                ];
            }

            return $info;

        } catch (\Exception $e) {
            throw new UploadException(
                "Failed to get video information: {$e->getMessage()}",
                ['video_path' => $videoPath],
                $e
            );
        }
    }

    private function applyVideoFilters($video, array $options, array $videoInfo): void
    {
        $filters = $video->filters();

        // Resize video
        if (isset($options['resize'])) {
            $width = $options['resize']['width'] ?? null;
            $height = $options['resize']['height'] ?? null;
            
            if ($width && $height) {
                $filters->resize(new Dimension($width, $height), $options['resize']['mode'] ?? 'fit');
                $this->logger->info('Video resize filter applied', [
                    'width' => $width,
                    'height' => $height,
                    'mode' => $options['resize']['mode'] ?? 'fit'
                ]);
            }
        }

        // Auto-resize based on quality preset
        if (isset($options['quality'])) {
            $this->applyQualityPreset($filters, $options['quality'], $videoInfo);
        }

        // Clip video duration
        if (isset($options['clip'])) {
            $start = $options['clip']['start'] ?? '00:00:00';
            $duration = $options['clip']['duration'] ?? null;
            
            if ($duration) {
                $filters->clip(TimeCode::fromString($start), TimeCode::fromString($duration));
                $this->logger->info('Video clip filter applied', [
                    'start' => $start,
                    'duration' => $duration
                ]);
            }
        }

        // Rotate video
        if (isset($options['rotate'])) {
            $angle = $options['rotate'];
            switch ($angle) {
                case 90:
                    $filters->rotate(VideoFilters::ROTATE_90);
                    break;
                case 180:
                    $filters->rotate(VideoFilters::ROTATE_180);
                    break;
                case 270:
                    $filters->rotate(VideoFilters::ROTATE_270);
                    break;
            }
            $this->logger->info('Video rotation filter applied', ['angle' => $angle]);
        }

        // Watermark
        if (isset($options['watermark'])) {
            $watermarkPath = $options['watermark']['path'] ?? null;
            if ($watermarkPath && file_exists($watermarkPath)) {
                $position = $options['watermark']['position'] ?? 'top-left';
                $filters->watermark($watermarkPath, $this->getWatermarkCoordinates($position));
                $this->logger->info('Video watermark filter applied', [
                    'watermark_path' => $watermarkPath,
                    'position' => $position
                ]);
            }
        }
    }

    private function applyQualityPreset($filters, string $quality, array $videoInfo): void
    {
        $presets = [
            'low' => ['width' => 640, 'height' => 480],
            'medium' => ['width' => 1280, 'height' => 720],
            'high' => ['width' => 1920, 'height' => 1080],
            'ultra' => ['width' => 3840, 'height' => 2160],
        ];

        if (isset($presets[$quality])) {
            $preset = $presets[$quality];
            $currentWidth = $videoInfo['video']['width'] ?? 1920;
            $currentHeight = $videoInfo['video']['height'] ?? 1080;
            
            // Only downscale, never upscale
            if ($currentWidth > $preset['width'] || $currentHeight > $preset['height']) {
                $filters->resize(new Dimension($preset['width'], $preset['height']), 'fit');
                $this->logger->info('Quality preset applied', [
                    'quality' => $quality,
                    'target_resolution' => $preset
                ]);
            }
        }
    }

    private function getOutputFormat(array $options)
    {
        $format = $options['format'] ?? 'mp4';
        
        switch (strtolower($format)) {
            case 'webm':
                return new WebM();
            case 'mp4':
            default:
                return new X264();
        }
    }

    private function configureCompression($format, array $options): void
    {
        // Video bitrate
        if (isset($options['video_bitrate'])) {
            $format->setVideoCodec('libx264');
            $format->setVideoBitrate($options['video_bitrate']);
        }

        // Audio bitrate
        if (isset($options['audio_bitrate'])) {
            $format->setAudioCodec('aac');
            $format->setAudioBitrate($options['audio_bitrate']);
        }

        // Compression preset
        if (isset($options['compression'])) {
            $preset = $options['compression'];
            $presets = [
                'ultrafast' => ['video_bitrate' => 5000, 'audio_bitrate' => 128],
                'fast' => ['video_bitrate' => 3000, 'audio_bitrate' => 128],
                'medium' => ['video_bitrate' => 2000, 'audio_bitrate' => 128],
                'slow' => ['video_bitrate' => 1500, 'audio_bitrate' => 128],
                'veryslow' => ['video_bitrate' => 1000, 'audio_bitrate' => 128],
            ];

            if (isset($presets[$preset])) {
                $format->setVideoCodec('libx264');
                $format->setVideoBitrate($presets[$preset]['video_bitrate']);
                $format->setAudioCodec('aac');
                $format->setAudioBitrate($presets[$preset]['audio_bitrate']);
                
                $this->logger->info('Compression preset applied', [
                    'preset' => $preset,
                    'settings' => $presets[$preset]
                ]);
            }
        }

        // Additional options
        if (isset($options['additional_params'])) {
            $format->setAdditionalParameters($options['additional_params']);
        }
    }

    private function getWatermarkCoordinates(string $position): array
    {
        $coordinates = [
            'top-left' => ['x' => 10, 'y' => 10],
            'top-right' => ['x' => 'main_w-overlay_w-10', 'y' => 10],
            'bottom-left' => ['x' => 10, 'y' => 'main_h-overlay_h-10'],
            'bottom-right' => ['x' => 'main_w-overlay_w-10', 'y' => 'main_h-overlay_h-10'],
            'center' => ['x' => '(main_w-overlay_w)/2', 'y' => '(main_h-overlay_h)/2'],
        ];

        return $coordinates[$position] ?? $coordinates['top-left'];
    }

    public function convertToMp4(string $inputPath, string $outputPath, array $options = []): string
    {
        $this->requireFFmpeg('video conversion to MP4');

        $this->logger->info('Converting video to MP4', [
            'input' => $inputPath,
            'output' => $outputPath
        ]);

        $mp4Options = array_merge([
            'format' => 'mp4',
            'compression' => 'medium',
        ], $options);

        return $this->process($inputPath, $outputPath, $mp4Options);
    }

    public function compressVideo(string $inputPath, string $outputPath, string $compressionLevel = 'medium'): string
    {
        $this->requireFFmpeg('video compression');

        $this->logger->info('Compressing video', [
            'input' => $inputPath,
            'output' => $outputPath,
            'compression' => $compressionLevel
        ]);

        $options = [
            'format' => 'mp4',
            'compression' => $compressionLevel,
        ];

        return $this->process($inputPath, $outputPath, $options);
    }
} 