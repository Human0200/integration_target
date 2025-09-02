 # 🏢 Company API - Техническая документация

## 📋 Общее описание

API для работы с компаниями в Bitrix24 CRM, включает создание, поиск, обновление компаний и управление реквизитами.

## 📍 Базовый URL

```
/local/ajax/handler.php
или
btx.targetco.ru/local/ajax/handler.php
```

## 📡 Формат запроса

Все запросы отправляются методом **POST** с параметрами в формате `application/x-www-form-urlencoded`.

## 📥 Общая структура запроса

```http
POST /local/ajax/handler.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

action={название_действия}&params[параметр1]={значение1}&params[параметр2]={значение2}
```

## 📤 Формат ответа

Все ответы возвращаются в формате JSON:

```json
{
  "success": true/false,
  "data": {...} | "error": "описание ошибки"
}
```

## 🔧 Доступные методы

### 1. findOrCreateCompany - Поиск или создание компании

**Описание:** Ищет компанию по телефону или email, если не находит - создает новую.

**Параметры:**
```php
$params = [
    'properties' => [
        'TITLE' => 'Название компании',     // Обязательно
        'PHONE' => '+7 495 123-45-67',     // Телефон (опционально)
        'EMAIL' => 'company@example.com',  // Email (опционально)
        'NAME' => 'Контактное лицо'        // Имя контактного лица (опционально)
    ]
]
```

**Пример запроса:**
```javascript
const formData = new FormData();
formData.append('action', 'findOrCreateCompany');
formData.append('params[properties][TITLE]', 'ООО Тестовая компания');
formData.append('params[properties][PHONE]', '+7 495 123-45-67');
formData.append('params[properties][EMAIL]', 'info@testcompany.com');
```

**Пример ответа:**
```json
{
  "success": true,
  "data": {
    "companyId": 123
  }
}
```

### 2. updateCompany - Обновление данных компании

**Описание:** Обновляет поля существующей компании.

**Параметры:**
```php
$params = [
    'companyId' => 123,  // ID компании (обязательно)
     {          // Поля для обновления
        'TITLE' => 'Новое название',
        'COMMENTS' => 'Комментарий'
        // Другие поля CRM компании
    }
]
```

**Пример запроса:**
```javascript
const formData = new FormData();
formData.append('action', 'updateCompany');
formData.append('params[companyId]', '123');
formData.append('params[data][TITLE]', 'ООО Обновленная компания');
formData.append('params[data][COMMENTS]', 'Тестовое обновление');
```

**Пример ответа:**
```json
{
  "success": true,
  "data": {
    "companyId": 123
  }
}
```

### 3. createRequisites - Создание реквизитов компании

**Описание:** Создает или обновляет реквизиты компании.

**Параметры:**
```php
$params = [
    'companyId' => 123,  // ID компании (обязательно)
    'requisites' => [    // Реквизиты
        'INN' => '1234567890',           // ИНН
        'KPP' => '123456789',            // КПП
        'OGRN' => '1234567890123',       // ОГРН (опционально)
        'ADDRESS' => 'Адрес компании',   // Адрес (опционально)
        'PHONE' => '+7 495 123-45-67',   // Телефон (опционально)
        'EMAIL' => 'requisites@company.ru', // Email (опционально)
        'CONTACT_PERSON' => 'Иванов И.И.',  // Контактное лицо (опционально)
        'RESPONSIBLE_PERSON' => 'Петров П.П.', // Ответственное лицо (опционально)
        'COMMENT' => 'Комментарий к реквизитам' // Комментарий (опционально)
    ]
]
```

**Пример запроса:**
```javascript
const formData = new FormData();
formData.append('action', 'createRequisites');
formData.append('params[companyId]', '123');
formData.append('params[requisites][INN]', '1234567890');
formData.append('params[requisites][KPP]', '123456789');
formData.append('params[requisites][ADDRESS]', '123456, Москва, ул. Тестовая, д. 1');
```

**Пример ответа:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "message": "Реквизиты успешно созданы",
    "requisiteId": 456
  }
}
```

## 📱 Примеры использования

### JavaScript (с использованием FormData)

```javascript
// Создание компании
async function createCompany() {
    const formData = new FormData();
    formData.append('action', 'findOrCreateCompany');
    formData.append('params[properties][TITLE]', 'ООО Тестовая компания');
    formData.append('params[properties][PHONE]', '+7 495 123-45-67');
    formData.append('params[properties][EMAIL]', 'info@testcompany.com');
    
    const response = await fetch('/local/ajax/handler.php', {
        method: 'POST',
        body: formData
    });
    
    const result = await response.json();
    console.log(result);
}

// Создание реквизитов
async function createRequisites(companyId) {
    const formData = new FormData();
    formData.append('action', 'createRequisites');
    formData.append('params[companyId]', companyId);
    formData.append('params[requisites][INN]', '1234567890');
    formData.append('params[requisites][KPP]', '123456789');
    formData.append('params[requisites][ADDRESS]', '123456, Москва, ул. Тестовая, д. 1');
    
    const response = await fetch('/local/ajax/handler.php', {
        method: 'POST',
        body: formData
    });
    
    const result = await response.json();
    console.log(result);
}
```

## 🛠 Особенности работы

### Поиск компании
- Поиск осуществляется по телефону и email
- Поддерживаются различные форматы телефонов:
  - `+7 495 123-45-67`
  - `8 (495) 123-45-67`
  - `74951234567`
  - `84951234567`

### Реквизиты
- При создании реквизитов существующие реквизиты удаляются
- Это предотвращает дублирование ИНН и других данных
- Поддерживаются все стандартные поля реквизитов

### Обработка ошибок
- Все ошибки возвращаются в формате `{"success": false, "error": "описание"}`
- Логирование доступно в файле `company_manager.log` в папке класса

## 🔐 Требования

- Bitrix24 с установленным модулем CRM
- PHP 7.1+
- Модуль `leadspace.integrationtarget` (если используется)

## 📊 Коды ответов

| Код | Описание |
|-----|----------|
| 200 | Успешный запрос |
| 400 | Неверный формат запроса |
| 403 | Доступ запрещен |
| 500 | Внутренняя ошибка сервера |
