import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StatusBadge from '../ui/StatusBadge.vue'

describe('StatusBadge', () => {
  it('renders correct label for success status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'success' } })
    expect(wrapper.text()).toContain('Success')
  })

  it('renders correct label for failed status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'failed' } })
    expect(wrapper.text()).toContain('Failed')
  })

  it('renders correct label for running status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'running' } })
    expect(wrapper.text()).toContain('Running')
  })

  it('renders correct label for pending status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'pending' } })
    expect(wrapper.text()).toContain('Pending')
  })

  it('renders correct label for timeout status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'timeout' } })
    expect(wrapper.text()).toContain('Timeout')
  })

  it('falls back to raw status for unknown values', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'custom_unknown' } })
    expect(wrapper.text()).toContain('custom_unknown')
  })

  it('applies success CSS class', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'success' } })
    expect(wrapper.find('span').classes()).toContain('badge-success')
  })

  it('applies danger CSS class for failed', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'failed' } })
    expect(wrapper.find('span').classes()).toContain('badge-danger')
  })

  it('shows pulse dot when pulse prop is true and status is running', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'running', pulse: true } })
    expect(wrapper.find('.pulse-dot').exists()).toBe(true)
  })

  it('does not show pulse dot when status is not running', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'success', pulse: true } })
    expect(wrapper.find('.pulse-dot').exists()).toBe(false)
  })

  it('applies smaller size with sm prop', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'success', size: 'sm' } })
    const classes = wrapper.find('span').classes().join(' ')
    expect(classes).toContain('text-[10px]')
  })
})
