<?php
require_once '/var/www/vkbot/vendor/autoload.php';
require_once '/var/www/vkbot/config.php';

class VKGOIDA {
    private $vk;
    private $access_token;
    private $group_id;

    public function __construct(VK\Client\VKApiClient $vk, string $access_token, int $group_id) {
        $this->vk = $vk;
        $this->access_token = $access_token;
        $this->group_id = $group_id;
    }

    /**
     * Отправка сообщения с поддержкой клавиатур и вложений
     */
    public function sendMessage(int $user_id, string $message, array $params = []): void {
        try {
            $default_params = [
                'user_id'    => $user_id,
                'message'    => $message,
                'random_id'  => rand(1, 1e6),
                'v'          => '5.131' // Актуальная версия API
            ];

            // Добавляем клавиатуру, если передана
            if (isset($params['keyboard'])) {
                $default_params['keyboard'] = $params['keyboard'];
            }

            // Добавляем вложения (фото, документы и т.д.)
            if (isset($params['attachment'])) {
                $default_params['attachment'] = $params['attachment'];
            }

            $merged_params = array_merge($default_params, $params);
            
            error_log("Sending message: " . print_r($merged_params, true));
            $response = $this->vk->messages()->send($this->access_token, $merged_params);
            error_log("VK API Response: " . print_r($response, true));

        } catch (Exception $e) {
            error_log("VK API error: " . $e->getMessage());
        }
    }
    
    /**
     * Отправка фото из альбома группы
     */
    public function sendPhoto(int $user_id, string $photo_id, string $message = ''): void {
        try {
            $attachment = "photo-{$this->group_id}_{$photo_id}";
            $this->sendMessage($user_id, $message, ['attachment' => $attachment]);
        } catch (Exception $e) {
            error_log("VK Photo send error: " . $e->getMessage());
        }
    }

    /**
     * Создание кастомной клавиатуры
     * 
     * @param array $buttons Массив кнопок
     * @param bool $one_time Скрывать клавиатуру после использования
     * @param bool $inline Инлайн-режим
     * @return string JSON-строка клавиатуры
     */
    public function createKeyboard(array $buttons, bool $one_time = true, bool $inline = false): string {
        return json_encode([
            'one_time' => $one_time,
            'inline' => $inline,
            'buttons' => $buttons
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Создание текстовой кнопки
     * 
     * @param string $label Текст на кнопке
     * @param string $color Цвет (primary, secondary, positive, negative)
     * @param array $payload Произвольные данные
     * @return array Структура кнопки
     */
    public function createTextButton(string $label, string $color = 'primary', array $payload = []): array {
        return [
            'action' => [
                'type' => 'text',
                'label' => $label,
                'payload' => json_encode($payload)
            ],
            'color' => $color
        ];
    }

}