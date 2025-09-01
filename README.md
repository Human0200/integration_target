# Contact API Documentation

API для работы с контактами в Битрикс24 CRM. Позволяет создавать, искать и обновлять контакты с фильтрацией по компаниям.

## 📋 Содержание

- [Установка](#установка)
- [Базовое использование](#базовое-использование)
- [API методы](#api-методы)
- [Примеры кода](#примеры-кода)
- [Обработка ошибок](#обработка-ошибок)
- [FAQ](#faq)

## 🚀 Установка

### 1. Разместите файлы

**Класс FindContact:**
```
/local/modules/leadspace.integrationtarget/lib/classes/FindContact.php
```

**API хандлер:**
```
/local/ajax/handler.php
```

### 2. Структура модуля

Убедитесь что ваш модуль имеет правильную структуру:
```
/local/modules/leadspace.integrationtarget/
├── include.php
└── lib/
    └── classes/
        └── FindContact.php
```

### 3. Права доступа

Убедитесь что веб-сервер имеет права на чтение файлов модуля.

## 🔧 Базовое использование

### JavaScript (Fetch API)

```javascript
async function createContact() {
    const response = await fetch('/local/ajax/handler.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'findOrCreateContact',
            'params[properties][NAME]': 'Иван Петров',
            'params[properties][PHONE]': '+7 999 123-45-67',
            'params[properties][EMAIL]': 'ivan@example.com',
            'params[properties][COMPANY_ID]': '1'
        })
    });
    
    const result = await response.json();
    console.log(result);
}
```

### PHP (cURL)

```php
$data = [
    'action' => 'findOrCreateContact',
    'params' => [
        'properties' => [
            'NAME' => 'Иван Петров',
            'PHONE' => '+7 999 123-45-67',
            'EMAIL' => 'ivan@example.com',
            'COMPANY_ID' => '1'
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://your-domain.com/local/ajax/handler.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);
```

## 📡 API методы

### 1. findOrCreateContact

Находит существующий контакт или создает новый.

**Параметры:**
```javascript
{
    action: 'findOrCreateContact',
    params: {
        properties: {
            NAME: string,           // Имя (обязательно если нет FIO)
            FIO: string,            // ФИО (альтернатива NAME)
            LAST_NAME: string,      // Фамилия (опционально)
            PHONE: string,          // Телефон (обязательно если нет EMAIL)
            EMAIL: string,          // Email (обязательно если нет PHONE)
            COMPANY_ID: number      // ID компании (обязательно)
        }
    }
}
```

**Ответ при успехе:**
```json
{
    "success": true,
    "data": {
        "contactId": 123
    }
}
```

**Ответ при ошибке:**
```json
{
    "success": false,
    "error": "Не удалось найти или создать контакт"
}
```

### 2. updateContact

Обновляет данные существующего контакта.

**Параметры:**
```javascript
{
    action: 'updateContact',
    params: {
        contactId: number,      // ID контакта
        data: {
            NAME: string,       // Новое имя
            LAST_NAME: string,  // Новая фамилия
            COMMENTS: string,   // Комментарий
            // ... другие поля контакта
        }
    }
}
```

**Ответ:**
```json
{
    "success": true,
    "data": {
        "contactId": 123
    }
}
```

### 3. createAddress

Заглушка для создания адреса (пока не реализована).

**Параметры:**
```javascript
{
    action: 'createAddress',
    params: {
        requisites: {
            city: string,
            street: string,
            zip: string
        }
    }
}
```

## 💡 Примеры кода

### Создание контакта с проверкой

```javascript
async function createContactSafely(contactData) {
    try {
        const formData = new FormData();
        formData.append('action', 'findOrCreateContact');
        
        // Добавляем свойства контакта
        Object.entries(contactData).forEach(([key, value]) => {
            formData.append(`params[properties][${key}]`, value);
        });
        
        const response = await fetch('/local/ajax/handler.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Контакт создан/найден:', result.data.contactId);
            return result.data.contactId;
        } else {
            console.error('Ошибка API:', result.error);
            return null;
        }
    } catch (error) {
        console.error('Сетевая ошибка:', error);
        return null;
    }
}

// Использование
const contactId = await createContactSafely({
    NAME: 'Петр',
    LAST_NAME: 'Сидоров',
    PHONE: '+7 999 888-77-66',
    EMAIL: 'petr@company.com',
    COMPANY_ID: '1'
});
```

### Массовое создание контактов

```javascript
async function bulkCreateContacts(contacts) {
    const results = [];
    
    for (const contact of contacts) {
        try {
            const result = await createContactSafely(contact);
            results.push({
                contact,
                contactId: result,
                success: !!result
            });
            
            // Пауза между запросами
            await new Promise(resolve => setTimeout(resolve, 200));
        } catch (error) {
            results.push({
                contact,
                error: error.message,
                success: false
            });
        }
    }
    
    return results;
}

// Использование
const contactsToCreate = [
    { NAME: 'Клиент 1', PHONE: '+7 999 111-11-11', COMPANY_ID: '1' },
    { NAME: 'Клиент 2', PHONE: '+7 999 222-22-22', COMPANY_ID: '1' },
    // ...
];

const results = await bulkCreateContacts(contactsToCreate);
console.log('Результаты:', results);
```

### Поиск и обновление контакта

```javascript
async function findAndUpdateContact(phone, updateData) {
    // Сначала найдем контакт
    const searchResult = await fetch('/local/ajax/handler.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'findOrCreateContact',
            'params[properties][PHONE]': phone,
            'params[properties][COMPANY_ID]': '1'
        })
    });
    
    const searchData = await searchResult.json();
    
    if (!searchData.success) {
        console.error('Контакт не найден');
        return false;
    }
    
    // Теперь обновим контакт
    const updateResult = await fetch('/local/ajax/handler.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'updateContact',
            'params[contactId]': searchData.data.contactId,
            ...Object.entries(updateData).reduce((acc, [key, value]) => {
                acc[`params[data][${key}]`] = value;
                return acc;
            }, {})
        })
    });
    
    return await updateResult.json();
}

// Использование
const result = await findAndUpdateContact('+7 999 123-45-67', {
    NAME: 'Обновленное имя',
    COMMENTS: 'Клиент обновлен'
});
```

## ⚠️ Обработка ошибок

### Типы ошибок

1. **Сетевые ошибки** - проблемы с подключением
2. **Ошибки валидации** - неверные параметры
3. **Ошибки CRM** - проблемы с Битрикс24
4. **Ошибки модуля** - модуль не найден или не загружен

### Пример обработки

```javascript
async function safeAPICall(action, params) {
    try {
        const response = await fetch('/local/ajax/handler.php', {
            method: 'POST',
            body: new URLSearchParams({
                action,
                ...Object.entries(params).reduce((acc, [key, value]) => {
                    if (typeof value === 'object') {
                        Object.entries(value).forEach(([subKey, subValue]) => {
                            acc[`params[${key}][${subKey}]`] = subValue;
                        });
                    } else {
                        acc[`params[${key}]`] = value;
                    }
                    return acc;
                }, {})
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Unknown API error');
        }

        return result;

    } catch (error) {
        console.error('API Error:', error.message);
        
        // Можно добавить уведомления пользователю
        if (error.message.includes('Module not found')) {
            alert('Модуль CRM не установлен');
        } else if (error.message.includes('HTTP 500')) {
            alert('Ошибка сервера. Попробуйте позже');
        } else {
            alert('Произошла ошибка: ' + error.message);
        }
        
        return null;
    }
}
```

## 🔍 Особенности поиска

### Поиск по телефону

API автоматически обрабатывает различные форматы телефонов:

- `+7 999 123-45-67`
- `8 (999) 123-45-67`
- `79991234567`
- `89991234567`
- `+79991234567`
- `7 999 123 45 67`

Все эти форматы приведутся к единому виду для поиска.

### Фильтрация по компании

API ищет контакты **только** с заполненным `COMPANY_ID`. Это означает:

- Если передан `COMPANY_ID` - поиск только в этой компании
- Если не передан - поиск среди всех контактов с любой компанией
- Контакты без компании игнорируются

## ❓ FAQ

### Q: Что делать если контакт не создается?

A: Проверьте:
1. Передан ли телефон или email
2. Указан ли COMPANY_ID
3. Загружен ли модуль CRM
4. Есть ли права на создание контактов

### Q: Как найти контакт без создания нового?

A: API всегда сначала ищет существующий контакт. Если он найден - возвращается его ID без создания дубликата.

### Q: Можно ли искать контакты без привязки к компании?

A: Нет, API специально фильтрует только контакты с заполненным COMPANY_ID.

### Q: Поддерживаются ли другие поля контакта?

A: В методе `updateContact` можно передать любые стандартные поля контактов Битрикс24.

### Q: Что происходит при обновлении телефонов/email?

A: API проверяет существующие номера и email, добавляя новые только если их еще нет у контакта.

## 🔧 Отладка

Для отладки проблем:

1. Проверьте логи PHP: `/var/log/php_errors.log`
2. Включите отладку в коде:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```
3. Используйте тестовый интерфейс для проверки работы API
4. Проверьте права доступа к файлам модуля
