<template>
    <div class="default-main">
        <div class="crud-title">{{ t('crud.crud.start') }}</div>
        <div class="start-opt">
            <el-row :gutter="20">
                <el-col :xs="24" :span="6">
                    <div @click="changeStep('create')" class="start-item suspension">
                        <div class="start-item-title">{{ t('crud.crud.create') }}</div>
                        <div class="start-item-remark">{{ t('crud.crud.New background CRUD from zero') }}</div>
                    </div>
                </el-col>
                <el-col @click="onShowAI" :xs="24" :span="6">
                    <div class="start-item suspension">
                        <div class="start-item-title">{{ t('crud.crud.AI design') }}</div>
                        <div class="start-item-remark">{{ t('crud.crud.Describe requirements and generate table design') }}</div>
                    </div>
                </el-col>
                <el-col @click="onShowTableDialog" :xs="24" :span="6">
                    <div class="start-item suspension">
                        <div class="start-item-title">{{ t('crud.crud.Select Data Table') }}</div>
                        <div class="start-item-remark">{{ t('crud.crud.Select a designed data table from the database') }}</div>
                    </div>
                </el-col>
                <el-col @click="onShowLog" :xs="24" :span="6">
                    <div class="start-item suspension">
                        <div class="start-item-title">{{ t('crud.crud.CRUD record') }}</div>
                        <div class="start-item-remark">{{ t('crud.crud.Start with previously generated CRUD code') }}</div>
                    </div>
                </el-col>
            </el-row>
            <el-row justify="center">
                <el-col :span="20" class="ba-markdown crud-tips suspension">
                    <b>{{ t('crud.crud.Fast experience') }}</b>
                    <ol>
                        <li>
                            {{ t('crud.crud.experience 1 1') }}
                            <a target="_blank" href="https://doc.buildadmin.com/guide/other/developerMustSee.html" rel="noopener noreferrer">
                                {{ t('crud.crud.experience 1 2') }}
                            </a>
                            {{ t('crud.crud.experience 1 3') }}
                        </li>
                        <li>
                            {{ t('crud.crud.experience 2 1') }}
                            <code>{{ t('crud.crud.create') }}</code>
                            {{ t('crud.crud.or') }}
                            <code> {{ t('crud.crud.experience 2 2') }}{{ t('crud.crud.experience 2 3') }} </code>
                        </li>
                        <li>
                            {{ t('crud.crud.experience 3 1') }} <code>{{ t('crud.crud.experience 3 2') }}</code>
                            {{ t('crud.crud.experience 3 3') }}
                            <code>{{ t('crud.crud.experience 3 4') }}</code>
                        </li>
                    </ol>
                    <el-alert v-if="!isDev()" class="no-dev" type="warning" :show-icon="true" :closable="false">
                        <template #title>
                            <span>{{ t('crud.crud.experience 4 1') }}</span>
                            <a target="_blank" href="https://doc.buildadmin.com/guide/other/developerMustSee.html" rel="noopener noreferrer">
                                {{ t('crud.crud.experience 4 2') }}
                            </a>
                            <span>
                                {{ t('crud.crud.experience 4 3') }}<code>{{ t('crud.crud.experience 4 4') }}</code>
                            </span>
                        </template>
                    </el-alert>
                </el-col>
            </el-row>

            <el-dialog
                class="ba-operate-dialog select-table-dialog"
                v-model="state.tableDialog.visible"
                :title="state.tableDialog.type == 'sql' ? t('crud.crud.Please enter SQL') : t('crud.crud.Please select a data table')"
                :destroy-on-close="true"
            >
                <el-form :label-width="140" @keyup.enter="onSubmit()" class="select-table-form" ref="formRef" :model="state.tableForm" :rules="rules">
                    <template v-if="state.tableDialog.type == 'sql'">
                        <el-input
                            class="sql-input"
                            prop="sql"
                            ref="sqlInputRef"
                            v-model="state.tableForm.sql"
                            type="textarea"
                            :placeholder="t('crud.crud.table create SQL')"
                            :rows="10"
                            @keyup.enter.stop=""
                            @keyup.ctrl.enter="onSubmit()"
                        />
                    </template>
                    <template v-else-if="state.tableDialog.type == 'db'">
                        <FormItem
                            :label="t('Database connection')"
                            v-model="state.tableForm.databaseConnection"
                            type="remoteSelect"
                            :label-width="140"
                            :block-help="t('Database connection help')"
                            :input-attr="{
                                pk: 'key',
                                field: 'key',
                                remoteUrl: getDatabaseConnectionListUrl,
                                onChange: onDatabaseChange,
                            }"
                            :placeholder="t('Please select field', { field: t('Database connection') })"
                        />
                        <FormItem
                            :label="t('crud.crud.data sheet')"
                            v-model="state.tableForm.table"
                            type="remoteSelect"
                            :key="state.tableForm.databaseConnection"
                            :placeholder="t('crud.crud.Please select a data table')"
                            :label-width="140"
                            :block-help="t('crud.crud.data sheet help')"
                            :input-attr="{
                                pk: 'table',
                                field: 'comment',
                                params: {
                                    connection: state.tableForm.databaseConnection,
                                    samePrefix: 1,
                                    excludeTable: [
                                        'area',
                                        'token',
                                        'captcha',
                                        'admin_group_access',
                                        'config',
                                        'admin_log',
                                        'user_money_log',
                                        'user_score_log',
                                    ],
                                },
                                remoteUrl: getTableListUrl,
                                onRow: onTableStartChange,
                            }"
                            prop="table"
                        />
                        <el-alert
                            v-if="state.successRecord"
                            class="success-record-alert"
                            :title="t('crud.crud.The selected table has already generated records You are advised to start with historical records')"
                            :show-icon="true"
                            :closable="false"
                            type="warning"
                        />
                    </template>
                </el-form>
                <template #footer>
                    <div :style="{ width: 'calc(100% * 0.9)' }">
                        <el-button @click="state.tableDialog.visible = false">{{ $t('Cancel') }}</el-button>
                        <el-button :loading="state.loading" @click="onSubmit()" v-blur type="primary">{{ t('Confirm') }}</el-button>
                        <el-button v-if="state.successRecord" @click="onLogStart" v-blur type="success">
                            {{ t('crud.crud.Start with the historical record') }}
                        </el-button>
                    </div>
                </template>
            </el-dialog>

            <CrudLog v-model="state.showLog" />
            <AIDialog v-model="state.showAI" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, useTemplateRef } from 'vue'
import { checkCrudLog } from '/@/api/backend/crud'
import FormItem from '/@/components/formItem/index.vue'
import { changeStep } from '/@/views/backend/crud/index'
import { ElNotification } from 'element-plus'
import type { FormItemRule } from 'element-plus'
import { buildValidatorData } from '/@/utils/validate'
import AIDialog from '/@/views/backend/crud/ai.vue'
import CrudLog from '/@/views/backend/crud/log.vue'
import { useI18n } from 'vue-i18n'
import { getDatabaseConnectionListUrl, getTableListUrl } from '/@/api/common'

const { t } = useI18n()
const formRef = useTemplateRef('formRef')
const sqlInputRef = useTemplateRef('sqlInputRef')

const state = reactive({
    tableDialog: {
        type: '' as 'db' | 'sql',
        visible: false,
    },
    tableForm: {
        sql: '',
        table: '',
        databaseConnection: '',
    },
    showAI: false,
    showLog: false,
    loading: false,
    successRecord: 0,
})

const onShowAI = () => {
    state.showAI = true
}

const onShowLog = () => {
    state.showLog = true
}

const onShowTableDialog = () => {
    state.tableDialog.type = 'db'
    state.tableDialog.visible = true
    state.successRecord = 0
    state.tableForm.sql = ''
    state.tableForm.table = ''
    state.tableForm.databaseConnection = ''
}

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    table: [buildValidatorData({ name: 'required', message: t('crud.crud.Please select a data table') })],
})

const onSubmit = () => {
    if (state.tableDialog.type == 'sql' && !state.tableForm.sql) {
        ElNotification({
            type: 'error',
            message: t('crud.crud.Please enter the table creation SQL'),
        })
        return
    }
    formRef.value?.validate((valid) => {
        if (valid) {
            changeStep(state.tableDialog.type, {
                table: state.tableForm.table,
                sql: state.tableForm.sql,
                databaseConnection: state.tableForm.databaseConnection,
            })
        }
    })
}

const onDatabaseChange = () => {
    state.successRecord = 0
    state.tableForm.table = ''
}

const onTableStartChange = () => {
    if (state.tableForm.table) {
        // 检查是否有CRUD记录
        state.loading = true
        checkCrudLog(state.tableForm.table, state.tableForm.databaseConnection)
            .then((res) => {
                state.successRecord = res.data.id
            })
            .finally(() => {
                state.loading = false
            })
    }
}

const onLogStart = () => {
    if (state.successRecord) {
        changeStep('log', { logId: state.successRecord.toString() })
    }
}

const isDev = () => {
    return import.meta.env.DEV
}
</script>

<style scoped lang="scss">
:deep(.select-table-dialog) .el-dialog__body {
    height: unset;
    .select-table-form {
        width: 88%;
        padding: 40px 0;
    }
    .success-record-alert {
        width: calc(100% - 140px);
        margin-left: 140px;
        margin-bottom: 30px;
        margin-top: -10px;
    }
}
.crud-title {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--el-font-size-extra-large);
    font-weight: bold;
    padding-top: 120px;
}
.start-opt {
    display: block;
    width: 70%;
    margin: 40px auto;
}
.start-item {
    background-color: #e1eaf9;
    border-radius: var(--el-border-radius-base);
    padding: 25px;
    margin-bottom: 20px;
    cursor: pointer;
}
.start-item-title {
    font-size: var(--el-font-size-large);
    color: var(--ba-color-primary-light);
}
.start-item-remark {
    display: block;
    line-height: 18px;
    min-height: 48px;
    padding-top: 12px;
    color: #92969a;
}
.sql-input {
    margin: 20px 0;
}
.crud-tips {
    margin-top: 60px;
    padding: 20px;
    background-color: rgba($color: #ffffff, $alpha: 0.6);
    border-radius: var(--el-border-radius-base);
    color: var(--el-color-info);
    b {
        font-size: 15px;
        padding-left: 10px;
    }
    .no-dev {
        margin-top: 10px;
    }
}
@at-root .dark {
    .start-item {
        background-color: #1d1e1f;
    }
    .crud-tips {
        background-color: rgba($color: #1d1e1f, $alpha: 0.4);
    }
}
</style>
