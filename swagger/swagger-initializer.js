window.onload = function() {
  const spec = {
    "openapi": "3.0.0",
    "info": {
      "title": "Bitrix24 CRM Integration API",
      "description": "API для интеграции с CRM Bitrix24 - управление контактами и компаниями",
      "version": "1.0.0",
      "contact": {
        "name": "API Support",
        "email": "support@example.com"
      }
    },
    "servers": [
      {
        "url": "https://btx.targetco.ru/local/ajax",
        "description": "Production server"
      }
    ],
    "tags": [
      {
        "name": "Contacts",
        "description": "Операции с контактами"
      },
      {
        "name": "Companies", 
        "description": "Операции с компаниями"
      }
    ],
    "paths": {
      "/handler.php?action=findOrCreateContact": {
        "post": {
          "tags": ["Contacts"],
          "summary": "Найти или создать контакт",
          "requestBody": {
            "required": true,
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "example": "findOrCreateContact"
                    },
                    "params": {
                      "type": "object",
                      "properties": {
                        "properties": {
                          "type": "object",
                          "properties": {
                            "FIO": {"type": "string", "example": "Тест"},
                            "NAME": {"type": "string", "example": "Иван"},
                            "LAST_NAME": {"type": "string", "example": "Иванов"},
                            "PHONE": {"type": "string", "example": "79991234567"},
                            "EMAIL": {"type": "string", "example": "test@example.com"},
                            "COMPANY_ID": {"type": "integer", "example": 123}
                          }
                        }
                      }
                    }
                  },
                  "example": {
                    "action": "findOrCreateContact",
                    "params": {
                      "properties": {
                        "FIO": "Тест",
                        "EMAIL": "test@example.com",
                        "PHONE": "79991234567"
                      }
                    }
                  }
                }
              }
            }
          },
          "responses": {
            "200": {
              "description": "Успешный ответ",
              "content": {
                "application/json": {
                  "schema": {
                    "type": "object",
                    "properties": {
                      "success": {"type": "boolean", "example": true},
                      "data": {
                        "type": "object", 
                        "properties": {
                          "contactId": {"type": "integer", "example": 456}
                        }
                      }
                    },
                    "example": {
                      "success": true,
                      "data": {"contactId": 456}
                    }
                  }
                }
              }
            }
          }
        }
      },
      "/handler.php?action=updateContact": {
        "post": {
          "tags": ["Contacts"],
          "summary": "Обновить контакт",
          "requestBody": {
            "required": true,
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string", 
                      "example": "updateContact"
                    },
                    "params": {
                      "type": "object",
                      "properties": {
                        "contactId": {"type": "integer", "example": 456},
                        "data": {
                          "type": "object",
                          "properties": {
                            "NAME": {"type": "string", "example": "Петр"},
                            "LAST_NAME": {"type": "string", "example": "Иванов"},
                            "EMAIL": {"type": "string", "example": "petr@example.com"},
                            "COMPANY_ID": {"type": "integer", "example": 789},
                          }
                        }
                      }
                    }
                  },
                  "example": {
                    "action": "updateContact",
                    "params": {
                      "contactId": 456,
                      "data": {
                        "NAME": "Петр",
                        "EMAIL": "petr@example.com"
                      }
                    }
                  }
                }
              }
            }
          },
          "responses": {
            "200": {
              "description": "Успешный ответ",
              "content": {
                "application/json": {
                  "schema": {
                    "type": "object",
                    "properties": {
                      "success": {"type": "boolean", "example": true},
                      "data": {
                        "type": "object",
                        "properties": {
                          "contactId": {"type": "integer", "example": 456}
                        }
                      }
                    },
                    "example": {
                      "success": true, 
                      "data": {"contactId": 456}
                    }
                  }
                }
              }
            }
          }
        }
      },
      "/handler.php?action=deleteContact": {
        "post": {
          "tags": ["Contacts"],
          "summary": "Удалить контакт",
          "requestBody": {
            "required": true,
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "example": "deleteContact"
                    },
                    "params": {
                      "type": "object", 
                      "properties": {
                        "contactId": {"type": "integer", "example": 456}
                      }
                    }
                  },
                  "example": {
                    "action": "deleteContact",
                    "params": {
                      "contactId": 456
                    }
                  }
                }
              }
            }
          },
          "responses": {
            "200": {
              "description": "Успешный ответ", 
              "content": {
                "application/json": {
                  "schema": {
                    "type": "object",
                    "properties": {
                      "success": {"type": "boolean", "example": true},
                      "data": {
                        "type": "object",
                        "properties": {
                          "message": {"type": "string", "example": "Контакт успешно удален"},
                          "contactId": {"type": "integer", "example": 456}
                        }
                      }
                    },
                    "example": {
                      "success": true,
                      "data": {
                        "message": "Контакт успешно удален", 
                        "contactId": 456
                      }
                    }
                  }
                }
              }
            }
          }
        }
      },
      "/handler.php?action=findOrCreateCompany": {
        "post": {
          "tags": ["Companies"],
          "summary": "Найти или создать компанию", 
          "requestBody": {
            "required": true,
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "example": "findOrCreateCompany"
                    },
                    "params": {
                      "type": "object",
                      "properties": {
                        "properties": {
                          "type": "object",
                          "properties": {
                            "TITLE": {"type": "string", "example": "ООО Тестовая Компания"},
                            "INN": {"type": "string", "example": "1234567890"},
                            "KPP": {"type": "string", "example": "123456789"},
                            "PHONE": {"type": "string", "example": "+74951234567"},
                            "EMAIL": {"type": "string", "example": "info@testcompany.ru"},
                            "ADDRESS": {"type": "string", "example": "г. Москва, ул. Тестовая, д. 1"}
                          }
                        }
                      }
                    }
                  },
                  "example": {
                    "action": "findOrCreateCompany", 
                    "params": {
                      "properties": {
                        "TITLE": "ООО Тестовая Компания",
                        "INN": "1234567890",
                        "PHONE": "+74951234567"
                      }
                    }
                  }
                }
              }
            }
          },
          "responses": {
            "200": {
              "description": "Успешный ответ",
              "content": {
                "application/json": {
                  "schema": {
                    "type": "object",
                    "properties": {
                      "success": {"type": "boolean", "example": true},
                      "data": {
                        "type": "object",
                        "properties": {
                          "companyId": {"type": "integer", "example": 123}
                        }
                      }
                    },
                    "example": {
                      "success": true,
                      "data": {"companyId": 123}
                    }
                  }
                }
              }
            }
          }
        }
      },
"/handler.php?action=updateCompany": {
  "post": {
    "tags": ["Companies"],
    "summary": "Обновить компанию",
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "properties": {
              "action": {
                "type": "string",
                "example": "updateCompany",
                "description": "Идентификатор действия. Должен быть `updateCompany`."
              },
              "params": {
                "type": "object",
                "properties": {
                  "companyId": {
                    "type": "integer",
                    "example": 123,
                    "description": "Уникальный ID компании в CRM."
                  },
                  "data": {
                    "type": "object",
                    "properties": {
                      "TITLE": {
                        "type": "string",
                        "example": "ООО Новая Компания",
                        "description": "Полное наименование компании."
                      },
                      "INN": {
                        "type": "string",
                        "example": "9876543210",
                        "description": "ИНН компании (10 или 12 цифр)."
                      },
                      "PHONE": {
                        "type": "string",
                        "example": "+74959876543",
                        "description": "Контактный телефон компании в формате +7..."
                      },
                      "EMAIL": {
                        "type": "string",
                        "example": "new@company.ru",
                        "description": "Корпоративный email."
                      },
                      "UF_CRM_1760515262922": {
                        "type": "string",
                        "example": "12345",
                        "description": "Пользовательское поле: ID компании в Таргете."
                      },
                      "COMMENTS": {
                        "type": "string",
                        "example": "Комментарии по компании",
                        "description": "Произвольные заметки менеджера."
                      },
                      "UF_CRM_1756716976955": {
                        "type": "string",
                        "example": "123",
                        "description": "Пользовательское поле: значение из списка (например, тип клиента)."
                      }
                    },
                    "description": "Данные для обновления. Можно передавать только изменяемые поля."
                  }
                },
                "required": ["companyId", "data"],
                "description": "Параметры запроса обновления компании."
              }
            },
            "required": ["action", "params"],
            "example": {
              "action": "updateCompany",
              "params": {
                "companyId": 123,
                "data": {
                  "TITLE": "ООО Новая Компания",
                  "EMAIL": "new@company.ru",
                  "UF_CRM_1760515262922": 12345,
                  "COMMENTS": "Комментарии по компании",
                  "UF_CRM_1756716976955": 123
                }
              }
            }
          }
        }
      }
    },
    "responses": {
      "200": {
        "description": "Успешный ответ",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "properties": {
                "success": {
                  "type": "boolean",
                  "example": true,
                  "description": "Флаг успешного выполнения операции."
                },
                "data": {
                  "type": "object",
                  "properties": {
                    "companyId": {
                      "type": "integer",
                      "example": 123,
                      "description": "ID обновлённой компании."
                    }
                  },
                  "description": "Данные результата операции."
                }
              },
              "example": {
                "success": true,
                "data": {
                  "companyId": 123
                }
              }
            }
          }
        }
      }
    }
  }
},
      "/handler.php?action=deleteCompany": {
        "post": {
          "tags": ["Companies"],
          "summary": "Удалить компанию",
          "requestBody": {
            "required": true,
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "example": "deleteCompany"
                    },
                    "params": {
                      "type": "object",
                      "properties": {
                        "companyId": {"type": "integer", "example": 123}
                      }
                    }
                  },
                  "example": {
                    "action": "deleteCompany",
                    "params": {
                      "companyId": 123
                    }
                  }
                }
              }
            }
          },
          "responses": {
            "200": {
              "description": "Успешный ответ",
              "content": {
                "application/json": {
                  "schema": {
                    "type": "object",
                    "properties": {
                      "success": {"type": "boolean", "example": true},
                      "data": {
                        "type": "object",
                        "properties": {
                          "message": {"type": "string", "example": "Компания успешно удалена"},
                          "companyId": {"type": "integer", "example": 123}
                        }
                      }
                    },
                    "example": {
                      "success": true,
                      "data": {
                        "message": "Компания успешно удалена",
                        "companyId": 123
                      }
                    }
                  }
                }
              }
            }
          }
        }
      },
      "/handler.php?action=createRequisites": {
        "post": {
          "tags": ["Companies"],
          "summary": "Создать реквизиты компании",
          "requestBody": {
            "required": true,
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "example": "createRequisites"
                    },
                    "params": {
                      "type": "object",
                      "properties": {
                        "companyId": {"type": "integer", "example": 123},
                        "requisites": {
                          "type": "object",
                          "properties": {
                            "RQ_INN": {"type": "string", "example": "1234567890"},
                            "RQ_KPP": {"type": "string", "example": "123456789"},
                            "RQ_OGRN": {"type": "string", "example": "1234567890123"},
                            "RQ_ADDR": {"type": "string", "example": "г. Москва, ул. Тестовая, д. 1"}
                          }
                        }
                      }
                    }
                  },
                  "example": {
                    "action": "createRequisites",
                    "params": {
                      "companyId": 123,
                      "requisites": {
                        "RQ_INN": "1234567890",
                        "RQ_KPP": "123456789",
                        "RQ_OGRN": "1234567890123"
                      }
                    }
                  }
                }
              }
            }
          },
          "responses": {
            "200": {
              "description": "Успешный ответ",
              "content": {
                "application/json": {
                  "schema": {
                    "type": "object",
                    "properties": {
                      "success": {"type": "boolean", "example": true},
                      "data": {
                        "type": "object",
                        "properties": {
                          "message": {"type": "string", "example": "Реквизиты успешно созданы"}
                        }
                      }
                    },
                    "example": {
                      "success": true,
                      "data": {
                        "message": "Реквизиты успешно созданы"
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  };

  window.ui = SwaggerUIBundle({
    spec: spec,
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [
      SwaggerUIBundle.presets.apis,
      SwaggerUIStandalonePreset
    ],
    layout: "StandaloneLayout"
  });
};