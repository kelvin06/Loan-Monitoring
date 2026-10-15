<script setup lang="ts">
import { computed } from 'vue'
export interface TableColumn { key: string; label: string; sortable?: boolean }

const props = defineProps<{
  columns: TableColumn[]
  rows: object[]
  sortBy?: string
  sortDirection?: 'asc' | 'desc'
  emptyText?: string
}>()

const emit = defineEmits<{ sort: [key: string] }>()
const rowData = computed(() => props.rows as Record<string, unknown>[])
</script>

<template>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr>
        <th v-for="column in columns" :key="column.key" scope="col">
          <button v-if="column.sortable !== false" class="table-sort" type="button" :aria-sort="sortBy === column.key ? (sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" :aria-label="`Sort by ${column.label}`" @click="emit('sort', column.key)">
            {{ column.label }}<span aria-hidden="true">{{ sortBy === column.key ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span>
          </button>
          <span v-else>{{ column.label }}</span>
        </th>
      </tr></thead>
      <tbody>
        <tr v-for="(row, rowIndex) in rowData" :key="String(row.id ?? rowIndex)">
          <td v-for="column in columns" :key="column.key">
            <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] ?? '—' }}</slot>
          </td>
        </tr>
        <tr v-if="!rowData.length"><td :colspan="columns.length" class="empty">{{ emptyText ?? 'No records found.' }}</td></tr>
      </tbody>
    </table>
  </div>
</template>
